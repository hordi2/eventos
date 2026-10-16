<?php

declare(strict_types=1);

namespace App\Support\Payments;

use App\Support\Money;
use Stripe\StripeClient;

final class StripeCheckoutProvider implements CardCheckoutProvider
{
    public function createCheckoutSession(int $orderId, int $organizationId, Money $amount, string $successUrl, string $cancelUrl): string
    {
        $client = new StripeClient(config('services.stripe.secret'));

        $metadata = ['order_id' => (string) $orderId, 'organization_id' => (string) $organizationId];

        $session = $client->checkout->sessions->create([
            'mode' => 'payment',
            // 'card' suffit à faire apparaître Apple Pay / Google Pay chez
            // Stripe Checkout (détection automatique selon l'appareil de
            // l'acheteur), pas besoin de les lister séparément (M5.3).
            'payment_method_types' => ['card'],
            'line_items' => [[
                'price_data' => [
                    'currency' => mb_strtolower($amount->currency()),
                    'unit_amount' => $amount->amountMinor(),
                    'product_data' => ['name' => "Commande #{$orderId}"],
                ],
                'quantity' => 1,
            ]],
            // Sur les deux objets : checkout.session.completed porte la
            // session, payment_intent.payment_failed porte le PaymentIntent
            // — RecordStripeWebhookEvent doit retrouver organization_id et
            // order_id sur l'un comme sur l'autre.
            'metadata' => $metadata,
            'payment_intent_data' => [
                'metadata' => $metadata,
            ],
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            // Stripe refuse moins de 30 minutes (minimum de l'API) : la
            // réservation de stock (15 minutes, CreateOrder) reste plus
            // courte, mais la commande peut désormais être payée après son
            // expiration — voir le traitement de ce cas dans
            // RecordStripeWebhookEvent (remboursement automatique).
            'expires_at' => now()->addMinutes(30)->getTimestamp(),
        ]);

        return (string) $session->url;
    }

    public function refund(string $paymentIntentId, Money $amount): string
    {
        $client = new StripeClient(config('services.stripe.secret'));

        $refund = $client->refunds->create([
            'payment_intent' => $paymentIntentId,
            'amount' => $amount->amountMinor(),
        ]);

        return (string) $refund->id;
    }
}
