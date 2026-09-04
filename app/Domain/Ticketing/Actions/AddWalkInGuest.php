<?php

declare(strict_types=1);

namespace App\Domain\Ticketing\Actions;

use App\Domain\Organization\Models\Organization;
use App\Domain\Ticketing\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * Inscription rapide au comptoir (M7.1.7, T-063) : un seul billet à la fois,
 * pas de panier — « formulaire minimal ». Réutilise CreateOrder pour la
 * réservation de capacité (même garanties de concurrence, voir son
 * docblock) plutôt que de créer la commande à la main.
 *
 * Encaissement éventuel seulement : un type de billet gratuit passe
 * directement à Paid via MarkOrderPaid (rien à encaisser), un type payant
 * suit le même chemin que ChooseOnSitePayment + RecordOnSitePayment
 * (T-054) — le montant réellement encaissé est celui de la commande,
 * aucun champ « montant reçu » séparé : c'est au personnel de gérer le
 * rendu de monnaie, pas au système (même principe que RecordOnSitePayment).
 */
final class AddWalkInGuest
{
    public function __construct(
        private readonly CreateOrder $createOrder,
        private readonly ChooseOnSitePayment $chooseOnSitePayment,
        private readonly RecordOnSitePayment $recordOnSitePayment,
        private readonly MarkOrderPaid $markOrderPaid,
    ) {}

    public function handle(
        Organization $organization,
        int $eventId,
        int $ticketTypeId,
        string $name,
        string $email,
        ?string $phone,
        User $collector,
    ): Order {
        Gate::forUser($collector)->authorize('checkIn', $organization);

        $order = $this->createOrder->handle(
            organizationId: $organization->id,
            eventId: $eventId,
            buyer: ['name' => $name, 'email' => $email, 'phone' => $phone],
            items: [['ticket_type_id' => $ticketTypeId, 'quantity' => 1]],
            reservationKey: (string) Str::uuid(),
        );

        if ($order->total->isZero()) {
            $order = $this->markOrderPaid->handle($order, 'free', (string) Str::uuid(), $order->total, $collector);
        } else {
            $order = $this->chooseOnSitePayment->handle($order);
            $order = $this->recordOnSitePayment->handle($order, $collector, $order->total);
        }

        return $order->fresh(['items.tickets']);
    }
}
