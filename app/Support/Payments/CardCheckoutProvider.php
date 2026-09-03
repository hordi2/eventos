<?php

declare(strict_types=1);

namespace App\Support\Payments;

use App\Support\Money;

/**
 * Séparé de CreateStripeCheckout pour rester testable : le SDK Stripe fait
 * ses propres appels HTTP (Guzzle interne), jamais via
 * Illuminate\Support\Facades\Http — Http::fake() ne l'intercepterait pas.
 * Cette interface se lie (voir AppServiceProvider) et se remplace en test —
 * même mécanisme que WhatsappProvider (T-045bis).
 *
 * La vérification de signature de webhook n'a pas besoin de cette
 * abstraction : \Stripe\Webhook::constructEvent() est un calcul HMAC local,
 * sans appel réseau, donc directement testable (voir RecordStripeWebhookEvent).
 */
interface CardCheckoutProvider
{
    /**
     * @return string l'URL de paiement hébergée Stripe Checkout vers laquelle rediriger l'acheteur
     */
    public function createCheckoutSession(int $orderId, Money $amount, string $successUrl, string $cancelUrl): string;
}
