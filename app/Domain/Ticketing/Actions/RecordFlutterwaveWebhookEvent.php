<?php

declare(strict_types=1);

namespace App\Domain\Ticketing\Actions;

use App\Domain\Ticketing\Models\Order;
use App\Domain\Ticketing\Models\Payment;
use App\Support\MultiTenancy\CurrentOrganization;
use App\Support\Payments\InvalidWebhookSignatureException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Traite un événement de webhook Flutterwave de façon idempotente (règle
 * 4.4 du CLAUDE.md), même mécanique que RecordStripeWebhookEvent (T-052).
 *
 * Vérification de signature : Flutterwave calcule un HMAC-SHA256 encodé en
 * base64 du corps brut avec le hash secret configuré, transmis dans l'en-
 * tête flutterwave-signature — un calcul local, sans appel réseau.
 */
final class RecordFlutterwaveWebhookEvent
{
    public function __construct(
        private readonly CurrentOrganization $currentOrganization,
        private readonly MarkOrderPaid $markOrderPaid,
        private readonly FailOrderPayment $failOrderPayment,
    ) {}

    public function handle(string $payload, string $signatureHeader): void
    {
        $this->verifySignature($payload, $signatureHeader);

        /** @var array<string, mixed> $decoded */
        $decoded = json_decode($payload, true) ?? [];
        $eventId = (string) ($decoded['id'] ?? '');

        if ($eventId === '') {
            throw InvalidWebhookSignatureException::forProvider('flutterwave');
        }

        $inserted = DB::table('flutterwave_webhook_events')->insertOrIgnore([[
            'provider' => 'flutterwave',
            'event_id' => $eventId,
            'payload' => $payload,
            'processed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]]);

        if ($inserted === 0) {
            Log::info("Webhook Flutterwave déjà traité, ignoré : {$eventId}.");

            return;
        }

        $this->apply($decoded);
    }

    private function verifySignature(string $payload, string $signatureHeader): void
    {
        $secret = config('services.flutterwave.webhook_secret');

        if ($secret === null || $signatureHeader === '') {
            throw InvalidWebhookSignatureException::forProvider('flutterwave');
        }

        $expected = base64_encode(hash_hmac('sha256', $payload, (string) $secret, true));

        if (! hash_equals($expected, $signatureHeader)) {
            throw InvalidWebhookSignatureException::forProvider('flutterwave');
        }
    }

    /**
     * @param  array<string, mixed>  $decoded
     */
    private function apply(array $decoded): void
    {
        $data = $decoded['data'] ?? [];
        $chargeId = (string) ($data['id'] ?? '');
        $status = (string) ($data['status'] ?? '');

        if ($chargeId === '') {
            Log::warning('Webhook Flutterwave reçu sans identifiant de charge.', ['payload' => $decoded]);

            return;
        }

        // Sans global scope : à ce stade, aucune organisation n'est encore
        // connue — même raisonnement que RecordStripeWebhookEvent.
        $payment = Payment::query()->withoutGlobalScopes()->where('provider_payment_id', $chargeId)->first();

        if ($payment === null) {
            Log::warning("Webhook Flutterwave reçu pour une charge inconnue : {$chargeId}.");

            return;
        }

        $order = Order::query()->withoutGlobalScopes()->find($payment->order_id);

        if ($order === null) {
            return;
        }

        $this->currentOrganization->set($order->organization_id);

        match ($status) {
            'succeeded' => $this->markOrderPaid->handle($order, 'flutterwave', $chargeId, $payment->amount),
            'failed' => $this->failOrderPayment->handle($order, 'flutterwave', $chargeId, 'Paiement Mobile Money refusé.'),
            default => Log::info("Statut Flutterwave ignoré : {$status}."),
        };
    }
}
