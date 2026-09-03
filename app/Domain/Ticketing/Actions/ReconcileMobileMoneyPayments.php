<?php

declare(strict_types=1);

namespace App\Domain\Ticketing\Actions;

use App\Domain\Ticketing\Models\Order;
use App\Domain\Ticketing\Models\OrderStatus;
use App\Domain\Ticketing\Models\Payment;
use App\Domain\Ticketing\Models\PaymentStatus;
use App\Support\MultiTenancy\CurrentOrganization;
use App\Support\Payments\MobileMoneyChargeStatus;
use App\Support\Payments\MobileMoneyProvider;
use Illuminate\Support\Facades\Log;

/**
 * Réconciliation quotidienne avec le prestataire Mobile Money (T-053, AC :
 * « Réconciliation quotidienne automatique avec le fournisseur »),
 * filet de sécurité pour les paiements dont la confirmation par webhook ne
 * serait jamais arrivée (webhook manqué, panne temporaire...).
 *
 * Un paiement toujours "pending" chez le prestataire au moment de la
 * réconciliation, alors que la commande a déjà expiré (les 15 minutes de
 * réservation, T-051, sont bien plus courtes que le rythme quotidien de
 * cette tâche) n'est PAS forcé vers "paid" : le stock a déjà été relâché
 * et peut avoir été revendu. Ce cas — rare, argent capturé sans billet —
 * est seulement journalisé pour une revue manuelle, pas géré
 * automatiquement (hors périmètre de ce ticket).
 */
final class ReconcileMobileMoneyPayments
{
    public function __construct(
        private readonly CurrentOrganization $currentOrganization,
        private readonly MobileMoneyProvider $provider,
        private readonly MarkOrderPaid $markOrderPaid,
        private readonly FailOrderPayment $failOrderPayment,
    ) {}

    public function handle(): void
    {
        $pendingPayments = Payment::query()
            ->withoutGlobalScopes()
            ->where('provider', 'flutterwave')
            ->where('status', PaymentStatus::Pending)
            ->get();

        foreach ($pendingPayments as $payment) {
            $this->reconcileOne($payment);
        }
    }

    private function reconcileOne(Payment $payment): void
    {
        $status = $this->provider->getChargeStatus($payment->provider_payment_id);

        if ($status === MobileMoneyChargeStatus::Pending) {
            return;
        }

        $order = Order::query()->withoutGlobalScopes()->find($payment->order_id);

        if ($order === null) {
            return;
        }

        $this->currentOrganization->set($order->organization_id);

        if ($status === MobileMoneyChargeStatus::Succeeded) {
            if ($order->status !== OrderStatus::Pending) {
                Log::warning('Réconciliation Flutterwave : paiement confirmé pour une commande qui n\'est plus en attente — revue manuelle nécessaire.', [
                    'order_id' => $order->id,
                    'payment_id' => $payment->id,
                    'order_status' => $order->status->value,
                ]);

                return;
            }

            $this->markOrderPaid->handle($order, 'flutterwave', $payment->provider_payment_id, $payment->amount);

            return;
        }

        if ($order->status === OrderStatus::Pending) {
            $this->failOrderPayment->handle($order, 'flutterwave', $payment->provider_payment_id, 'Paiement Mobile Money refusé (réconciliation).');
        }
    }
}
