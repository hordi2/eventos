<?php

declare(strict_types=1);

namespace App\Domain\Ticketing\Actions;

use App\Domain\Ticketing\InvalidOrderTransitionException;
use App\Domain\Ticketing\Models\Order;
use App\Domain\Ticketing\Models\OrderStatus;
use App\Domain\Ticketing\Models\Payment;
use App\Domain\Ticketing\Models\PaymentStatus;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Échec de paiement : contrairement à l'expiration (délai écoulé), le stock
 * est libéré immédiatement — inutile d'attendre les 15 minutes puisque le
 * prestataire a déjà répondu.
 */
final class FailOrderPayment
{
    public function __construct(private readonly ReleaseOrderCapacity $releaseOrderCapacity) {}

    public function handle(Order $order, string $provider, ?string $providerPaymentId, string $reason): Order
    {
        if ($order->status !== OrderStatus::Pending) {
            throw InvalidOrderTransitionException::notPending($order->id, $order->status);
        }

        DB::transaction(function () use ($order, $provider, $providerPaymentId, $reason): void {
            Payment::query()->create([
                'organization_id' => $order->organization_id,
                'order_id' => $order->id,
                'provider' => $provider,
                'provider_payment_id' => $providerPaymentId,
                'status' => PaymentStatus::Failed,
                'failure_reason' => $reason,
                'amount' => $order->total,
                'attempted_at' => CarbonImmutable::now(),
                'failed_at' => CarbonImmutable::now(),
            ]);

            $order->update([
                'status' => OrderStatus::Failed,
                'failed_at' => CarbonImmutable::now(),
                'reserved_until' => null,
            ]);

            $this->releaseOrderCapacity->handle($order);
        });

        return $order->fresh(['payments']);
    }
}
