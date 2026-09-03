<?php

declare(strict_types=1);

namespace App\Domain\Ticketing\Actions;

use App\Domain\Ticketing\InvalidOrderTransitionException;
use App\Domain\Ticketing\Models\Order;
use App\Domain\Ticketing\Models\OrderStatus;
use App\Domain\Ticketing\Models\Payment;
use App\Domain\Ticketing\Models\PaymentStatus;
use App\Domain\Ticketing\Models\Ticket;
use App\Domain\Ticketing\Models\TicketStatus;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Confirmation de paiement (M5.4 : « Commande confirmée -> Billets émis »).
 * Appelée par le webhook du prestataire une fois construit (Stripe = T-052,
 * Mobile Money = T-053) ou par l'enregistrement d'un paiement à l'arrivée
 * (T-054) — aucun des deux n'existe encore, provider/providerPaymentId sont
 * fournis tels quels par l'appelant.
 *
 * Idempotence des webhooks de paiement (§4.4 CLAUDE.md) : un
 * provider_payment_id déjà traité est ignoré silencieusement (log info),
 * jamais rejoué en double — la commande peut recevoir la même confirmation
 * plusieurs fois (retry réseau du prestataire).
 */
final class MarkOrderPaid
{
    public function handle(Order $order, string $provider, string $providerPaymentId, Money $amount): Order
    {
        if (Payment::query()->where('provider_payment_id', $providerPaymentId)->exists()) {
            Log::info('Paiement déjà traité, rejoué de façon idempotente.', [
                'order_id' => $order->id,
                'provider_payment_id' => $providerPaymentId,
            ]);

            return $order->fresh(['items.tickets', 'payments']);
        }

        if ($order->status !== OrderStatus::Pending) {
            throw InvalidOrderTransitionException::notPending($order->id, $order->status);
        }

        DB::transaction(function () use ($order, $provider, $providerPaymentId, $amount): void {
            Payment::query()->create([
                'organization_id' => $order->organization_id,
                'order_id' => $order->id,
                'provider' => $provider,
                'provider_payment_id' => $providerPaymentId,
                'status' => PaymentStatus::Succeeded,
                'amount' => $amount,
                'attempted_at' => CarbonImmutable::now(),
                'succeeded_at' => CarbonImmutable::now(),
            ]);

            $order->update([
                'status' => OrderStatus::Paid,
                'paid_at' => CarbonImmutable::now(),
                'reserved_until' => null,
            ]);

            foreach ($order->items as $item) {
                for ($i = 0; $i < $item->quantity; $i++) {
                    Ticket::query()->create([
                        'organization_id' => $order->organization_id,
                        'order_item_id' => $item->id,
                        'ticket_type_id' => $item->ticket_type_id,
                        'status' => TicketStatus::Valid,
                    ]);
                }
            }
        });

        return $order->fresh(['items.tickets', 'payments']);
    }
}
