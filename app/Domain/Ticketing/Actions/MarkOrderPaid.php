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
use App\Models\User;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Confirmation de paiement (M5.4 : « Commande confirmée -> Billets émis »).
 * Appelée par le webhook Stripe (T-052), Mobile Money (T-053), ou
 * RecordOnSitePayment pour un encaissement en espèces au check-in (T-054) —
 * le seul cas où $collector est renseigné (« opérateur » du rapport de
 * caisse).
 *
 * Accepte Pending ET PaymentOnSite comme point de départ : un paiement en
 * ligne part toujours de Pending, un paiement à l'arrivée part de
 * PaymentOnSite (l'invité a déjà choisi ce mode au moment de la commande,
 * voir ChooseOnSitePayment).
 *
 * Idempotence des webhooks de paiement (§4.4 CLAUDE.md) : un
 * provider_payment_id déjà traité est ignoré silencieusement (log info),
 * jamais rejoué en double — la commande peut recevoir la même confirmation
 * plusieurs fois (retry réseau du prestataire).
 */
final class MarkOrderPaid
{
    /**
     * @var list<OrderStatus>
     */
    private const PAYABLE_STATUSES = [OrderStatus::Pending, OrderStatus::PaymentOnSite];

    public function handle(Order $order, string $provider, string $providerPaymentId, Money $amount, ?User $collector = null): Order
    {
        if (Payment::query()->where('provider_payment_id', $providerPaymentId)->exists()) {
            Log::info('Paiement déjà traité, rejoué de façon idempotente.', [
                'order_id' => $order->id,
                'provider_payment_id' => $providerPaymentId,
            ]);

            return $order->fresh(['items.tickets', 'payments']);
        }

        if (! in_array($order->status, self::PAYABLE_STATUSES, true)) {
            throw InvalidOrderTransitionException::notPending($order->id, $order->status);
        }

        DB::transaction(function () use ($order, $provider, $providerPaymentId, $amount, $collector): void {
            Payment::query()->create([
                'organization_id' => $order->organization_id,
                'order_id' => $order->id,
                'provider' => $provider,
                'collected_by' => $collector?->id,
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
