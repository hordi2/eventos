<?php

declare(strict_types=1);

namespace App\Domain\Ticketing\Actions;

use App\Domain\Ticketing\InvalidOrderTransitionException;
use App\Domain\Ticketing\Models\Order;
use App\Domain\Ticketing\Models\OrderStatus;

/**
 * L'invité choisit de payer à l'arrivée plutôt qu'en ligne (D3, T-054).
 * Efface reserved_until : contrairement à un paiement en ligne, l'invité
 * n'a que 15 minutes pour payer avant expiration (T-051) — un paiement à
 * l'arrivée n'a pas cette contrainte, l'événement peut avoir lieu dans
 * plusieurs semaines. ExpireOrderJob, déjà programmé par CreateOrder, ne
 * fera donc rien à son échéance (la commande n'est plus "pending").
 *
 * $allowOnSitePayment est fourni par l'appelant (events.allow_on_site_payment,
 * Domain/Event) plutôt que résolu ici : Domain/Ticketing ne dépend jamais
 * directement d'un modèle de Domain/Event (section 3 du CLAUDE.md). Son
 * défaut à true couvre AddWalkInGuest (encaissement par le personnel au
 * comptoir, T-063) : ce réglage ne concerne que le choix de l'invité en
 * ligne, jamais le personnel sur place.
 */
final class ChooseOnSitePayment
{
    public function handle(Order $order, bool $allowOnSitePayment = true): Order
    {
        if (! $allowOnSitePayment) {
            throw InvalidOrderTransitionException::onSitePaymentDisabled($order->id);
        }

        if ($order->status !== OrderStatus::Pending) {
            throw InvalidOrderTransitionException::notPending($order->id, $order->status);
        }

        $order->update([
            'status' => OrderStatus::PaymentOnSite,
            'reserved_until' => null,
        ]);

        return $order->fresh();
    }
}
