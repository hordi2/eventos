<?php

declare(strict_types=1);

namespace App\Domain\Ticketing\Actions;

use App\Domain\Ticketing\Models\Donation;
use App\Domain\Ticketing\Models\Order;
use App\Domain\Ticketing\Models\OrderItem;
use App\Domain\Ticketing\Models\OrderStatus;
use App\Domain\Ticketing\Models\PriceTier;
use App\Domain\Ticketing\Models\TicketType;
use App\Domain\Ticketing\TicketsUnavailableException;
use App\Jobs\ExpireOrderJob;
use App\Support\Capacity\Data\ReservationOutcome;
use App\Support\Money;
use App\Support\MultiTenancy\CurrentOrganization;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Panier + réservation temporaire du stock (M5.4, T-051). event_id est un
 * simple entier, jamais un modèle Event — même règle que SubmitRegistration
 * (Domain/Ticketing ne dépend d'aucun modèle de Domain/Event).
 *
 * Pas de Gate::authorize ici : comme SubmitRegistration, c'est un point
 * d'entrée public (invité anonyme), pas une action d'organisateur.
 *
 * Réservation de capacité PUIS création de la commande, en deux temps
 * distincts plutôt qu'une seule transaction englobante : le verrou Redis de
 * ReserveCapacity (ReservePriceTierCapacity/ReserveTicketTypeCapacity) est
 * relâché dès que SA PROPRE transaction se termine — si cet appel est
 * imbriqué dans la transaction plus large de CreateOrder, cette transaction
 * interne n'est en réalité qu'un SAVEPOINT, pas un vrai COMMIT : la ligne
 * réservée reste invisible aux autres connexions tant que la transaction
 * englobante n'a pas elle-même commité, alors que le verrou, lui, est déjà
 * libéré. Une commande concurrente peut alors relire un compte pas encore
 * à jour et survendre. Un test de concurrence sur 10 tentatives contre un
 * quota de 5 a mis ce défaut en évidence (6 acceptées) avant ce correctif —
 * voir tests/Concurrency/OrderConcurrencyTest.php.
 */
final class CreateOrder
{
    public function __construct(
        private readonly CurrentOrganization $currentOrganization,
        private readonly ReserveTicketTypeCapacity $reserveTicketTypeCapacity,
        private readonly ReleaseTicketTypeCapacity $releaseTicketTypeCapacity,
        private readonly ReservePriceTierCapacity $reservePriceTierCapacity,
        private readonly ReleasePriceTierCapacity $releasePriceTierCapacity,
        private readonly DetermineActivePriceTier $determineActivePriceTier,
    ) {}

    /**
     * @param  array{name: string, email: string, phone?: string|null}  $buyer
     * @param  list<array{ticket_type_id: int, quantity: int}>  $items
     */
    public function handle(
        int $organizationId,
        int $eventId,
        array $buyer,
        array $items,
        string $reservationKey,
        int $reservationMinutes = 15,
        ?Money $donation = null,
        ?string $donationCause = null,
    ): Order {
        $this->currentOrganization->set($organizationId);

        $existing = Order::query()->where('reservation_key', $reservationKey)->first();

        if ($existing !== null) {
            return $existing;
        }

        $this->assertValidBuyer($buyer);
        $this->assertValidItems($items);
        $this->assertValidDonation($donation);

        $resolved = [];

        try {
            foreach ($items as $item) {
                $resolved[] = $this->reserveItem($item, $reservationKey);
            }
        } catch (TicketsUnavailableException $exception) {
            $this->releaseResolved($resolved, $reservationKey);

            throw $exception;
        }

        $order = DB::transaction(function () use ($organizationId, $eventId, $buyer, $resolved, $reservationKey, $reservationMinutes, $donation, $donationCause): Order {
            $order = Order::query()->create([
                'organization_id' => $organizationId,
                'event_id' => $eventId,
                'buyer_name' => $buyer['name'],
                'buyer_email' => mb_strtolower(trim($buyer['email'])),
                'buyer_phone_e164' => $buyer['phone'] ?? null,
                'status' => OrderStatus::Pending,
                'reservation_key' => $reservationKey,
                'total' => Money::zero($resolved[0]['unit_amount']->currency()),
                'reserved_until' => CarbonImmutable::now()->addMinutes($reservationMinutes),
            ]);

            $total = null;

            foreach ($resolved as $line) {
                $lineTotal = $line['unit_amount']->multipliedBy($line['quantity']);
                $total = $total === null ? $lineTotal : $total->add($lineTotal);

                OrderItem::query()->create([
                    'organization_id' => $order->organization_id,
                    'order_id' => $order->id,
                    'ticket_type_id' => $line['ticket_type']->id,
                    'price_tier_id' => $line['tier']?->id,
                    'name' => $line['name'],
                    'quantity' => $line['quantity'],
                    'unit_amount' => $line['unit_amount'],
                ]);
            }

            if ($donation !== null) {
                $total = $total === null ? $donation : $total->add($donation);

                Donation::query()->create([
                    'organization_id' => $order->organization_id,
                    'order_id' => $order->id,
                    'amount' => $donation,
                    'cause' => $donationCause,
                ]);
            }

            $order->update(['total' => $total]);

            return $order;
        });

        ExpireOrderJob::dispatch($order->id, $organizationId)->delay($order->reserved_until);

        return $order->fresh(['items', 'donations']);
    }

    /**
     * @param  array{ticket_type_id: int, quantity: int}  $item
     * @return array{ticket_type: TicketType, tier: PriceTier|null, quantity: int, unit_amount: Money, name: string}
     */
    private function reserveItem(array $item, string $reservationKey): array
    {
        $ticketType = TicketType::query()->findOrFail($item['ticket_type_id']);
        $quantity = $item['quantity'];

        $this->assertQuantityWithinBounds($ticketType, $quantity);

        $typeKey = "{$reservationKey}:type:{$ticketType->id}";
        $typeOutcome = $this->reserveTicketTypeCapacity->handle($ticketType, $typeKey, $quantity);

        if ($typeOutcome->outcome === ReservationOutcome::Rejected) {
            throw TicketsUnavailableException::quotaReached($ticketType->id);
        }

        if ($ticketType->is_free) {
            return [
                'ticket_type' => $ticketType,
                'tier' => null,
                'quantity' => $quantity,
                'unit_amount' => Money::zero($ticketType->currency),
                'name' => $ticketType->name,
            ];
        }

        $tier = $this->determineActivePriceTier->handle($ticketType);

        if ($tier === null) {
            $this->releaseTicketTypeCapacity->handle($ticketType, $typeKey);

            throw TicketsUnavailableException::noActivePriceTier($ticketType->id);
        }

        $tierKey = "{$reservationKey}:tier:{$tier->id}";
        $tierOutcome = $this->reservePriceTierCapacity->handle($tier, $tierKey, $quantity);

        if ($tierOutcome->outcome === ReservationOutcome::Rejected) {
            $this->releaseTicketTypeCapacity->handle($ticketType, $typeKey);

            throw TicketsUnavailableException::tierQuotaReached($tier->id);
        }

        return [
            'ticket_type' => $ticketType,
            'tier' => $tier,
            'quantity' => $quantity,
            'unit_amount' => $tier->amount,
            'name' => "{$ticketType->name} — {$tier->name}",
        ];
    }

    /**
     * @param  list<array{ticket_type: TicketType, tier: PriceTier|null, quantity: int, unit_amount: Money, name: string}>  $resolved
     */
    private function releaseResolved(array $resolved, string $reservationKey): void
    {
        foreach ($resolved as $line) {
            $this->releaseTicketTypeCapacity->handle($line['ticket_type'], "{$reservationKey}:type:{$line['ticket_type']->id}");

            if ($line['tier'] !== null) {
                $this->releasePriceTierCapacity->handle($line['tier'], "{$reservationKey}:tier:{$line['tier']->id}");
            }
        }
    }

    /**
     * @param  array{name: string, email: string, phone?: string|null}  $buyer
     */
    private function assertValidBuyer(array $buyer): void
    {
        if (trim($buyer['name']) === '') {
            throw new InvalidArgumentException("Le nom de l'acheteur est requis.");
        }

        if (trim($buyer['email']) === '') {
            throw new InvalidArgumentException("L'e-mail de l'acheteur est requis.");
        }
    }

    /**
     * @param  list<array{ticket_type_id: int, quantity: int}>  $items
     */
    private function assertValidItems(array $items): void
    {
        if ($items === []) {
            throw new InvalidArgumentException('Une commande doit contenir au moins un billet.');
        }

        foreach ($items as $item) {
            if ($item['quantity'] < 1) {
                throw new InvalidArgumentException('La quantité de chaque ligne doit être d\'au moins 1.');
            }
        }
    }

    private function assertValidDonation(?Money $donation): void
    {
        if ($donation !== null && ! $donation->isPositive()) {
            throw new InvalidArgumentException('Le montant du don doit être strictement positif.');
        }
    }

    private function assertQuantityWithinBounds(TicketType $ticketType, int $quantity): void
    {
        if ($quantity < $ticketType->min_per_order) {
            throw new InvalidArgumentException(
                "Quantité insuffisante pour \"{$ticketType->name}\" (minimum {$ticketType->min_per_order} par commande)."
            );
        }

        if ($ticketType->max_per_order !== null && $quantity > $ticketType->max_per_order) {
            throw new InvalidArgumentException(
                "Quantité excessive pour \"{$ticketType->name}\" (maximum {$ticketType->max_per_order} par commande)."
            );
        }
    }
}
