<?php

declare(strict_types=1);

namespace App\Domain\Ticketing\Actions;

use App\Domain\Ticketing\InvalidOrderTransitionException;
use App\Domain\Ticketing\Models\Order;
use App\Domain\Ticketing\Models\OrderItem;
use App\Domain\Ticketing\Models\OrderStatus;
use App\Domain\Ticketing\TicketsUnavailableException;
use App\Jobs\ExpireOrderJob;
use App\Support\Capacity\Data\ReservationOutcome;
use Carbon\CarbonImmutable;

/**
 * Nouvelle tentative de paiement après un refus du prestataire (décision
 * produit : la même commande, pas une nouvelle). failed -> pending, seule
 * transition qui quitte failed.
 *
 * FailOrderPayment a libéré les places : elles sont reprises explicitement
 * (reacquireReleased) avec les mêmes clés que CreateOrder — que
 * ReleaseOrderCapacity sait donc toujours relâcher —, sur le palier d'origine et au prix d'origine, même
 * si ce palier n'est plus en vente (décision produit) — seul son quota
 * compte. Si une place manque, rien n'est retenu et la commande reste
 * failed. Pas de transaction englobante autour des réservations, pour la
 * même raison que CreateOrder (verrou relâché avant le COMMIT réel).
 *
 * $eventEndsAt reste une simple date : Ticketing ne dépend d'aucun modèle
 * de Domain/Event.
 */
final class RetryOrderPayment
{
    public function __construct(
        private readonly ReserveTicketTypeCapacity $reserveTicketTypeCapacity,
        private readonly ReservePriceTierCapacity $reservePriceTierCapacity,
        private readonly ReleaseOrderCapacity $releaseOrderCapacity,
        private readonly DetermineDonationPayableUntil $determineDonationPayableUntil,
    ) {}

    public function handle(Order $order, CarbonImmutable $eventEndsAt, int $reservationMinutes = 15): Order
    {
        if ($order->status !== OrderStatus::Failed) {
            throw InvalidOrderTransitionException::notFailed($order->id, $order->status);
        }

        $items = $order->items()->with(['ticketType', 'priceTier'])->get();

        try {
            $items->each(fn (OrderItem $item) => $this->reserveItem($order, $item));
        } catch (TicketsUnavailableException $exception) {
            $this->releaseOrderCapacity->handle($order);

            throw $exception;
        }

        $order->update([
            'status' => OrderStatus::Pending,
            'failed_at' => null,
            'reserved_until' => $items->isEmpty()
                ? $this->determineDonationPayableUntil->handle($eventEndsAt)
                : CarbonImmutable::now()->addMinutes($reservationMinutes),
        ]);

        ExpireOrderJob::dispatch($order->id, $order->organization_id)->delay($order->reserved_until);

        return $order->fresh(['items', 'donations']);
    }

    private function reserveItem(Order $order, OrderItem $item): void
    {
        // Type ou palier supprimé par l'organisateur depuis la commande : ses
        // places ne sont plus en vente.
        if ($item->ticketType === null || ($item->price_tier_id !== null && $item->priceTier === null)) {
            throw TicketsUnavailableException::removedFromSale($item->ticket_type_id);
        }

        $typeKey = "{$order->reservation_key}:type:{$item->ticketType->id}";

        if ($this->reserveTicketTypeCapacity->handle($item->ticketType, $typeKey, $item->quantity, reacquireReleased: true)->outcome === ReservationOutcome::Rejected) {
            throw TicketsUnavailableException::quotaReached($item->ticketType->id);
        }

        if ($item->priceTier === null) {
            return;
        }

        $tierKey = "{$order->reservation_key}:tier:{$item->priceTier->id}";

        if ($this->reservePriceTierCapacity->handle($item->priceTier, $tierKey, $item->quantity, reacquireReleased: true)->outcome === ReservationOutcome::Rejected) {
            throw TicketsUnavailableException::tierQuotaReached($item->priceTier->id);
        }
    }
}
