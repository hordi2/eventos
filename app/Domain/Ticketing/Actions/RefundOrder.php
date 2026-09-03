<?php

declare(strict_types=1);

namespace App\Domain\Ticketing\Actions;

use App\Domain\Ticketing\InvalidOrderTransitionException;
use App\Domain\Ticketing\Models\Order;
use App\Domain\Ticketing\Models\OrderStatus;
use App\Domain\Ticketing\Models\TicketStatus;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Remboursement (M5.5) : le mouvement d'argent effectif chez le prestataire
 * n'existe pas encore (aucun prestataire intégré, T-052+) — cette action ne
 * couvre que la machine à états et la libération du stock vendu, qui
 * redevient disponible à l'achat.
 */
final class RefundOrder
{
    public function __construct(private readonly ReleaseOrderCapacity $releaseOrderCapacity) {}

    public function handle(Order $order, User $refunder): Order
    {
        Gate::forUser($refunder)->authorize('refund', $order);

        if ($order->status !== OrderStatus::Paid) {
            throw InvalidOrderTransitionException::notPaid($order->id, $order->status);
        }

        DB::transaction(function () use ($order): void {
            $order->update([
                'status' => OrderStatus::Refunded,
                'refunded_at' => CarbonImmutable::now(),
            ]);

            foreach ($order->items as $item) {
                $item->tickets()->update(['status' => TicketStatus::Cancelled]);
            }

            $this->releaseOrderCapacity->handle($order);
        });

        return $order->fresh(['items.tickets']);
    }
}
