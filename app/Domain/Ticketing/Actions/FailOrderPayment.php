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
use Illuminate\Support\Facades\Log;

/**
 * Échec de paiement : contrairement à l'expiration (délai écoulé), le stock
 * est libéré immédiatement — inutile d'attendre les 15 minutes puisque le
 * prestataire a déjà répondu.
 *
 * Si providerPaymentId correspond à un Payment déjà créé "pending" (Mobile
 * Money, T-053 : InitiateMobileMoneyPayment crée la ligne dès la demande de
 * charge), cette ligne est mise à jour plutôt que dupliquée — sans quoi
 * l'unicité de provider_payment_id serait violée.
 *
 * Un échec déjà enregistré pour ce même paiement est ignoré (§4.4 CLAUDE.md,
 * comme MarkOrderPaid pour un succès) : sans quoi un refus en retard d'une
 * première tentative referait échouer la commande rouverte par
 * RetryOrderPayment pour une nouvelle tentative.
 */
final class FailOrderPayment
{
    public function __construct(private readonly ReleaseOrderCapacity $releaseOrderCapacity) {}

    public function handle(Order $order, string $provider, ?string $providerPaymentId, string $reason): Order
    {
        $existingPayment = $providerPaymentId !== null
            ? Payment::query()->where('provider_payment_id', $providerPaymentId)->first()
            : null;

        if ($existingPayment !== null && $existingPayment->status === PaymentStatus::Failed) {
            Log::info('Échec de paiement déjà traité, ignoré de façon idempotente.', [
                'order_id' => $order->id,
                'provider_payment_id' => $providerPaymentId,
            ]);

            return $order->fresh(['payments']);
        }

        if ($order->status !== OrderStatus::Pending) {
            throw InvalidOrderTransitionException::notPending($order->id, $order->status);
        }

        DB::transaction(function () use ($order, $provider, $providerPaymentId, $reason, $existingPayment): void {
            if ($existingPayment !== null) {
                $existingPayment->update([
                    'status' => PaymentStatus::Failed,
                    'failure_reason' => $reason,
                    'failed_at' => CarbonImmutable::now(),
                ]);
            } else {
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
            }

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
