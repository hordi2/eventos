<?php

declare(strict_types=1);

namespace App\Domain\Ticketing\Actions;

use App\Domain\Ticketing\InvalidOrderTransitionException;
use App\Domain\Ticketing\Models\Order;
use App\Domain\Ticketing\Models\OrderStatus;
use App\Domain\Ticketing\Models\Payment;
use App\Domain\Ticketing\Models\PaymentStatus;
use App\Support\Payments\MobileMoneyChargeRequest;
use App\Support\Payments\MobileMoneyProvider;
use App\Support\Payments\MobileMoneyProviderUnavailableException;
use Carbon\CarbonImmutable;

/**
 * Paiement Mobile Money asynchrone (M5.3, D3, T-053) : l'invité valide sur
 * son téléphone, la confirmation arrive plus tard par webhook
 * (RecordFlutterwaveWebhookEvent) ou, à défaut, par la réconciliation
 * quotidienne (ReconcileMobileMoneyPayments). La commande reste "pending"
 * avec sa fenêtre de réservation de 15 minutes déjà en place (T-051),
 * largement suffisante pour les délais de confirmation Mobile Money
 * (jusqu'à 5 minutes, AC de ce ticket).
 *
 * Mode dégradé (annexe C du CDC : « Fiabilité des agrégateurs Mobile
 * Money ») : si le prestataire est injoignable, bascule automatiquement
 * sur le paiement à l'arrivée (ChooseOnSitePayment, T-054) plutôt que de
 * faire échouer la réservation de l'invité.
 */
final class InitiateMobileMoneyPayment
{
    public function __construct(
        private readonly MobileMoneyProvider $provider,
        private readonly ChooseOnSitePayment $chooseOnSitePayment,
    ) {}

    public function handle(Order $order, string $countryCode, string $phoneNumber, string $network): Order
    {
        if ($order->status !== OrderStatus::Pending) {
            throw InvalidOrderTransitionException::notPending($order->id, $order->status);
        }

        [$firstName, $lastName] = $this->splitName($order->buyer_name);

        try {
            $chargeId = $this->provider->initiateCharge(new MobileMoneyChargeRequest(
                orderId: $order->id,
                amount: $order->total,
                countryCode: $countryCode,
                phoneNumber: $phoneNumber,
                network: $network,
                buyerEmail: $order->buyer_email,
                buyerFirstName: $firstName,
                buyerLastName: $lastName,
            ));
        } catch (MobileMoneyProviderUnavailableException) {
            return $this->chooseOnSitePayment->handle($order);
        }

        Payment::query()->create([
            'organization_id' => $order->organization_id,
            'order_id' => $order->id,
            'provider' => 'flutterwave',
            'provider_payment_id' => $chargeId,
            'status' => PaymentStatus::Pending,
            'amount' => $order->total,
            'attempted_at' => CarbonImmutable::now(),
        ]);

        return $order->fresh(['payments']);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function splitName(string $fullName): array
    {
        $parts = explode(' ', trim($fullName), 2);

        return [$parts[0], $parts[1] ?? $parts[0]];
    }
}
