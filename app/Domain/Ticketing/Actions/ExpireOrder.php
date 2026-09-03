<?php

declare(strict_types=1);

namespace App\Domain\Ticketing\Actions;

use App\Domain\Ticketing\Models\Order;
use App\Domain\Ticketing\Models\OrderStatus;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Expiration de la réservation de stock à l'échéance (M5.4). Distingue
 * l'abandon de panier (aucun paiement jamais tenté) d'une simple expiration
 * après tentative infructueuse ou interrompue — abandoned_at n'est renseigné
 * que dans le premier cas.
 *
 * Idempotent : ne fait rien si la commande n'est plus "pending" (déjà
 * payée/échouée/expirée entre-temps), ni si l'échéance n'est pas encore
 * atteinte — nécessaire car la file "sync" (utilisée en tests, et tout
 * environnement sans worker de queue) ignore le délai de ExpireOrderJob et
 * exécute le job immédiatement plutôt qu'après les 15 minutes.
 */
final class ExpireOrder
{
    public function __construct(private readonly ReleaseOrderCapacity $releaseOrderCapacity) {}

    public function handle(Order $order): Order
    {
        if ($order->status !== OrderStatus::Pending) {
            return $order;
        }

        if ($order->reserved_until !== null && $order->reserved_until->isFuture()) {
            return $order;
        }

        DB::transaction(function () use ($order): void {
            $abandoned = ! $order->payments()->exists();

            $order->update([
                'status' => OrderStatus::Expired,
                'expired_at' => CarbonImmutable::now(),
                'reserved_until' => null,
                'abandoned_at' => $abandoned ? CarbonImmutable::now() : null,
            ]);

            $this->releaseOrderCapacity->handle($order);
        });

        return $order->fresh();
    }
}
