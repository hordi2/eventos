<?php

declare(strict_types=1);

namespace App\Support\Payments;

use App\Support\Money;
use Stripe\StripeClient;

final class StripeCheckoutProvider implements CardCheckoutProvider
{
    public function createCheckoutSession(int $orderId, Money $amount, string $successUrl, string $cancelUrl): string
    {
        $client = new StripeClient(config('services.stripe.secret'));

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
            // — RecordStripeWebhookEvent doit retrouver order_id sur l'un
            // comme sur l'autre.
            'metadata' => ['order_id' => (string) $orderId],
            'payment_intent_data' => [
                'metadata' => ['order_id' => (string) $orderId],
            ],
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
        ]);

        return (string) $session->url;
    }
}
