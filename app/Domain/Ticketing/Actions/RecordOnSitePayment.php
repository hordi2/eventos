<?php

declare(strict_types=1);

namespace App\Domain\Ticketing\Actions;

use App\Domain\Ticketing\InvalidOrderTransitionException;
use App\Domain\Ticketing\Models\Order;
use App\Domain\Ticketing\Models\OrderStatus;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * Encaissement en espèces au check-in par le personnel (D3, T-054).
 * Réutilise l'ability checkIn déjà définie sur OrganizationPolicy (Owner,
 * Admin, Editor, DoorStaff — la même que pour scanner un billet).
 *
 * provider_payment_id n'existe pas pour un encaissement en espèces (aucun
 * prestataire externe) : un UUID généré ici tient ce rôle, uniquement pour
 * l'idempotence déjà assurée par MarkOrderPaid — sans conséquence si un
 * même encaissement était par erreur enregistré deux fois, chaque appel
 * génère une clé différente et créerait deux billets ; c'est au personnel,
 * pas au système, d'éviter le double encaissement physique.
 */
final class RecordOnSitePayment
{
    public function __construct(private readonly MarkOrderPaid $markOrderPaid) {}

    public function handle(Order $order, User $collector, Money $amountCollected): Order
    {
        Gate::forUser($collector)->authorize('checkIn', $order->organization);

        if ($order->status !== OrderStatus::PaymentOnSite) {
            throw InvalidOrderTransitionException::notPaymentOnSite($order->id, $order->status);
        }

        return $this->markOrderPaid->handle($order, 'cash', (string) Str::uuid(), $amountCollected, $collector);
    }
}
