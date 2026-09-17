<?php

declare(strict_types=1);

namespace App\Domain\Ticketing\Actions;

use App\Domain\Ticketing\Data\DonationPledge;
use App\Domain\Ticketing\Models\Donation;
use App\Domain\Ticketing\Models\Order;
use App\Domain\Ticketing\Models\OrderStatus;
use App\Jobs\ExpireOrderJob;
use App\Support\MultiTenancy\CurrentOrganization;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Don promis dans le formulaire d'inscription (T-056, décision produit :
 * « paiement après l'inscription ») : une commande sans billet, réglée par
 * le parcours de paiement habituel — carte, Mobile Money ou à l'accueil.
 * Aucune place à tenir, d'où une échéance longue plutôt que les 15 minutes
 * d'un panier de billets.
 *
 * Idempotent (§4.4 CLAUDE.md) : la même clé rend la commande déjà créée.
 */
final class CreateDonationOrder
{
    public function __construct(private readonly CurrentOrganization $currentOrganization) {}

    public function handle(int $organizationId, int $eventId, DonationPledge $pledge, string $reservationKey, CarbonImmutable $payableUntil): Order
    {
        $this->currentOrganization->set($organizationId);

        $existing = Order::query()->where('reservation_key', $reservationKey)->with('donations')->first();

        if ($existing !== null) {
            return $existing;
        }

        if (! $pledge->amount->isPositive()) {
            throw new InvalidArgumentException('Le montant du don doit être strictement positif.');
        }

        try {
            $order = DB::transaction(function () use ($organizationId, $eventId, $pledge, $reservationKey, $payableUntil): Order {
                $order = Order::query()->create([
                    'organization_id' => $organizationId,
                    'event_id' => $eventId,
                    'registration_id' => $pledge->registrationId,
                    'buyer_name' => $pledge->donorName,
                    'buyer_email' => mb_strtolower(trim($pledge->email)),
                    'buyer_phone_e164' => $pledge->phone,
                    'status' => OrderStatus::Pending,
                    'reservation_key' => $reservationKey,
                    'total' => $pledge->amount,
                    'reserved_until' => $payableUntil,
                ]);

                Donation::query()->create([
                    'organization_id' => $organizationId,
                    'order_id' => $order->id,
                    'amount' => $pledge->amount,
                    'cause' => $pledge->cause,
                    'donor_name' => $pledge->donorName,
                    'donor_company' => $pledge->company,
                    'donor_address' => $pledge->address === [] ? null : $pledge->address,
                    'is_anonymous' => $pledge->isAnonymous,
                ]);

                return $order;
            });
        } catch (UniqueConstraintViolationException) {
            // Deux confirmations simultanées (double clic) : l'autre a gagné.
            return Order::query()->where('reservation_key', $reservationKey)->with('donations')->firstOrFail();
        }

        ExpireOrderJob::dispatch($order->id, $organizationId)->delay($payableUntil);

        return $order->load('donations');
    }
}
