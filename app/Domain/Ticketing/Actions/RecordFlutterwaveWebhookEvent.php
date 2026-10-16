<?php

declare(strict_types=1);

namespace App\Domain\Ticketing\Actions;

use App\Domain\Ticketing\InvalidOrderTransitionException;
use App\Domain\Ticketing\Models\Order;
use App\Domain\Ticketing\Models\Payment;
use App\Support\MultiTenancy\CurrentOrganization;
use App\Support\Payments\InvalidWebhookSignatureException;
use App\Support\Payments\MobileMoneyProvider;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Traite un événement de webhook Flutterwave de façon idempotente (règle
 * 4.4 du CLAUDE.md), même mécanique que RecordStripeWebhookEvent (T-052) —
 * insertion et traitement dans une seule transaction, pour la même raison :
 * une exception imprévue ne doit jamais marquer l'événement traité sans
 * l'avoir réellement appliqué.
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
        private readonly MobileMoneyProvider $mobileMoneyProvider,
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

        DB::transaction(function () use ($decoded, $payload, $eventId): void {
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
        });
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
        $reference = (string) ($data['reference'] ?? '');

        if ($chargeId === '') {
            Log::warning('Webhook Flutterwave reçu sans identifiant de charge.', ['payload' => $decoded]);

            return;
        }

        $organizationId = $this->organizationIdFromReference($reference);

        if ($organizationId === null) {
            Log::warning("Webhook Flutterwave reçu sans organisation déductible de la référence : {$reference}.");

            return;
        }

        // organization_id vient de la référence posée à la création de la
        // charge (FlutterwaveMobileMoneyProvider), jamais d'une relecture du
        // paiement ou de la commande : la row-level security PostgreSQL
        // (règle 4.1 du CLAUDE.md) bloquerait cette relecture tant que le
        // contexte n'est pas posé — le rôle applicatif n'a jamais
        // BYPASSRLS (docker/postgres/init.sql).
        $this->currentOrganization->set($organizationId);

        $payment = Payment::query()->where('provider_payment_id', $chargeId)->first();

        if ($payment === null) {
            Log::warning("Webhook Flutterwave reçu pour une charge inconnue : {$chargeId}.");

            return;
        }

        $order = Order::query()->find($payment->order_id);

        if ($order === null) {
            return;
        }

        match ($status) {
            'succeeded' => $this->handleSucceeded($order, $chargeId, $payment),
            'failed' => $this->failOrderPayment->handle($order, 'flutterwave', $chargeId, 'Paiement Mobile Money refusé.'),
            default => Log::info("Statut Flutterwave ignoré : {$status}."),
        };
    }

    private function organizationIdFromReference(string $reference): ?int
    {
        if (preg_match('/^org(\d+)-order\d+-/', $reference, $matches) !== 1) {
            return null;
        }

        return (int) $matches[1];
    }

    private function handleSucceeded(Order $order, string $chargeId, Payment $payment): void
    {
        try {
            $this->markOrderPaid->handle($order, 'flutterwave', $chargeId, $payment->amount);
        } catch (InvalidOrderTransitionException) {
            // La confirmation Mobile Money peut arriver après que la
            // réservation de stock a expiré : l'argent est bien encaissé,
            // la commande ne peut plus l'accepter — on le rend aussitôt.
            Log::warning("Commande #{$order->id} payée après l'expiration de sa réservation : remboursement automatique.", [
                'charge_id' => $chargeId,
            ]);

            $this->mobileMoneyProvider->refund($chargeId, $payment->amount);
        }
    }
}
