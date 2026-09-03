<?php

declare(strict_types=1);

namespace App\Support\Payments;

/**
 * Couche d'abstraction fournisseur (M5.3, D3, T-053) : le métier
 * (InitiateMobileMoneyPayment, ReconcileMobileMoneyPayments) ne connaît que
 * cette interface, jamais Flutterwave directement — brancher un second
 * agrégateur (CinetPay...) n'exige qu'une nouvelle implémentation et un
 * rebind dans AppServiceProvider.
 */
interface MobileMoneyProvider
{
    /**
     * @return string l'identifiant de la charge chez le prestataire (provider_payment_id)
     *
     * @throws MobileMoneyProviderUnavailableException si le prestataire est injoignable
     */
    public function initiateCharge(MobileMoneyChargeRequest $request): string;

    public function getChargeStatus(string $chargeId): MobileMoneyChargeStatus;
}
