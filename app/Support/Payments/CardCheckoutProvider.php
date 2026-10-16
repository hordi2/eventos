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
     * $organizationId part en métadonnée (commande ET PaymentIntent) à côté
     * de $orderId : le webhook en a besoin pour poser le contexte multi-
     * tenant (CurrentOrganization) avant de relire la commande, elle-même
     * protégée par la row-level security (règle 4.1 du CLAUDE.md) — sans
     * ça, la recherche croisée organisation échoue toujours, le rôle
     * applicatif n'ayant jamais BYPASSRLS (docker/postgres/init.sql).
     *
     * @return string l'URL de paiement hébergée Stripe Checkout vers laquelle rediriger l'acheteur
     */
    public function createCheckoutSession(int $orderId, int $organizationId, Money $amount, string $successUrl, string $cancelUrl): string;

    /**
     * Remboursement total ou partiel d'un paiement déjà encaissé (M5.5).
     *
     * @return string l'identifiant du remboursement chez le prestataire
     */
    public function refund(string $paymentIntentId, Money $amount): string;
}
