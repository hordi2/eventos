<?php

declare(strict_types=1);

namespace App\Domain\Ticketing\Actions;

use App\Domain\Organization\Actions\RecordAuditLog;
use App\Domain\Ticketing\InvalidOrderTransitionException;
use App\Domain\Ticketing\Models\Order;
use App\Domain\Ticketing\Models\OrderStatus;
use App\Domain\Ticketing\Models\PaymentStatus;
use App\Domain\Ticketing\Models\TicketStatus;
use App\Models\User;
use App\Support\Money;
use App\Support\Payments\CardCheckoutProvider;
use App\Support\Payments\MobileMoneyProvider;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Remboursement (M5.5), total ou partiel : le mouvement d'argent se fait
 * réellement chez le prestataire qui a encaissé le dernier paiement réussi
 * de la commande — Stripe, Flutterwave, ou aucun appel pour un encaissement
 * en espèces (provider "cash", RecordOnSitePayment), que l'organisateur
 * rend lui-même.
 *
 * Un remboursement partiel ne touche ni les billets ni le stock vendu :
 * seul un remboursement qui atteint le total payé fait passer la commande à
 * Refunded et libère la capacité (règle 4.4 : toujours à partir de Paid,
 * jamais deux fois).
 */
final class RefundOrder
{
    public function __construct(
        private readonly ReleaseOrderCapacity $releaseOrderCapacity,
        private readonly CardCheckoutProvider $cardCheckoutProvider,
        private readonly MobileMoneyProvider $mobileMoneyProvider,
        private readonly RecordAuditLog $recordAuditLog,
    ) {}

    public function handle(Order $order, User $refunder, ?Money $amount = null): Order
    {
        Gate::forUser($refunder)->authorize('refund', $order);

        if ($order->status !== OrderStatus::Paid) {
            throw InvalidOrderTransitionException::notPaid($order->id, $order->status);
        }

        $payment = $order->payments()
            ->where('status', PaymentStatus::Succeeded)
            ->latest('succeeded_at')
            ->first();

        if ($payment === null) {
            throw InvalidOrderTransitionException::noSucceededPayment($order->id);
        }

        $remaining = $order->remainingRefundable();
        $refundAmount = $amount ?? $remaining;

        if ($refundAmount->greaterThan($remaining)) {
            throw InvalidOrderTransitionException::refundExceedsRemaining($order->id);
        }

        $providerRefundId = match ($payment->provider) {
            'stripe' => $this->cardCheckoutProvider->refund($payment->provider_payment_id, $refundAmount),
            'flutterwave' => $this->mobileMoneyProvider->refund($payment->provider_payment_id, $refundAmount),
            // Espèces (RecordOnSitePayment) : aucun prestataire à appeler,
            // l'organisateur rend l'argent lui-même.
            default => null,
        };

        $isFullRefund = $refundAmount->equals($remaining);

        DB::transaction(function () use ($order, $refundAmount, $isFullRefund): void {
            $order->update([
                'refunded_amount_minor' => $order->refunded_amount_minor + $refundAmount->amountMinor(),
                ...($isFullRefund ? ['status' => OrderStatus::Refunded, 'refunded_at' => CarbonImmutable::now()] : []),
            ]);

            if (! $isFullRefund) {
                return;
            }

            foreach ($order->items as $item) {
                $item->tickets()->update(['status' => TicketStatus::Cancelled]);
            }

            $this->releaseOrderCapacity->handle($order);
        });

        $this->recordAuditLog->handle('order.refunded', $refunder, $order, [
            'amount' => $refundAmount->format(),
            'full' => $isFullRefund,
            'provider' => $payment->provider,
            'provider_refund_id' => $providerRefundId,
        ]);

        return $order->fresh(['items.tickets', 'payments']);
    }
}
