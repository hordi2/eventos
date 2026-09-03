<?php

declare(strict_types=1);

namespace App\Domain\Ticketing\Actions;

use App\Domain\Ticketing\Models\Order;
use App\Support\Money;
use App\Support\MultiTenancy\CurrentOrganization;
use App\Support\Payments\InvalidWebhookSignatureException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Stripe\Event as StripeEvent;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;

/**
 * Traite un événement de webhook Stripe de façon idempotente (règle 4.4 du
 * CLAUDE.md), même mécanique que RecordWhatsappWebhookEvent (T-045bis) :
 * contrairement à Twilio, Stripe fournit son propre identifiant d'événement
 * stable (evt_...), utilisé tel quel comme event_id.
 *
 * La vérification de signature (Webhook::constructEvent) est un calcul HMAC
 * local sur le corps brut de la requête — aucun appel réseau, donc pas
 * besoin de l'abstraire derrière une interface pour rester testable
 * (contrairement à CreateStripeCheckout).
 */
final class RecordStripeWebhookEvent
{
    public function __construct(
        private readonly CurrentOrganization $currentOrganization,
        private readonly MarkOrderPaid $markOrderPaid,
        private readonly FailOrderPayment $failOrderPayment,
    ) {}

    public function handle(string $payload, string $signatureHeader): void
    {
        $event = $this->verifySignature($payload, $signatureHeader);

        $inserted = DB::table('stripe_webhook_events')->insertOrIgnore([[
            'provider' => 'stripe',
            'event_id' => $event->id,
            'payload' => $payload,
            'processed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]]);

        if ($inserted === 0) {
            Log::info("Webhook Stripe déjà traité, ignoré : {$event->id}.");

            return;
        }

        $this->apply($event);
    }

    private function verifySignature(string $payload, string $signatureHeader): StripeEvent
    {
        $webhookSecret = config('services.stripe.webhook_secret');

        if ($webhookSecret === null) {
            throw InvalidWebhookSignatureException::forProvider('stripe');
        }

        try {
            return Webhook::constructEvent($payload, $signatureHeader, $webhookSecret);
        } catch (SignatureVerificationException) {
            throw InvalidWebhookSignatureException::forProvider('stripe');
        }
    }

    private function apply(StripeEvent $event): void
    {
        $object = $event->data->object;
        $orderId = (int) ($object->metadata->order_id ?? 0);

        if ($orderId === 0) {
            Log::warning("Webhook Stripe reçu sans order_id en métadonnée : {$event->id}.");

            return;
        }

        // Sans global scope : à ce stade, aucune organisation n'est encore
        // connue — c'est justement ce que cette requête doit déterminer
        // (même raisonnement que RecordWhatsappWebhookEvent).
        $order = Order::query()->withoutGlobalScopes()->find($orderId);

        if ($order === null) {
            Log::warning("Webhook Stripe reçu pour une commande inconnue : #{$orderId}.");

            return;
        }

        $this->currentOrganization->set($order->organization_id);

        match ($event->type) {
            'checkout.session.completed' => $this->markOrderPaid->handle(
                $order,
                'stripe',
                (string) ($object->payment_intent ?? $object->id),
                Money::fromMinorUnits((int) $object['amount_total'], mb_strtoupper((string) $object['currency'])),
            ),
            'payment_intent.payment_failed' => $this->failOrderPayment->handle(
                $order,
                'stripe',
                (string) $object->id,
                (string) ($object->last_payment_error->message ?? 'Paiement refusé.'),
            ),
            default => Log::info("Événement Stripe ignoré : {$event->type}."),
        };
    }
}
