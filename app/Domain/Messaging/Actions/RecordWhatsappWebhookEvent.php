<?php

declare(strict_types=1);

namespace App\Domain\Messaging\Actions;

use App\Domain\Messaging\Models\WhatsappMessage;
use App\Domain\Messaging\Models\WhatsappMessageStatus;
use App\Support\MultiTenancy\CurrentOrganization;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Traite un rappel de statut Twilio de façon idempotente (règle 4.4 du
 * CLAUDE.md), même mécanique que RecordEmailWebhookEvent (T-043) : Twilio
 * n'envoie pas d'identifiant d'événement propre, contrairement à Postmark —
 * le couple (MessageSid, MessageStatus) est le meilleur identifiant stable
 * disponible, une transition de statut donnée n'arrivant qu'une fois.
 */
final class RecordWhatsappWebhookEvent
{
    public function __construct(
        private readonly CurrentOrganization $currentOrganization,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(array $payload): ?WhatsappMessage
    {
        $messageSid = (string) ($payload['MessageSid'] ?? $payload['SmsSid'] ?? '');
        $status = (string) ($payload['MessageStatus'] ?? '');
        $eventId = "{$messageSid}:{$status}";

        // insertOrIgnore() plutôt qu'upsert() avec un $update vide : voir
        // ApplyTagToContacts pour ce piège (Builder::upsert() retombe sur
        // un insert() nu qui échouerait sur ce même conflit).
        $inserted = DB::table('whatsapp_webhook_events')->insertOrIgnore([[
            'provider' => 'twilio',
            'event_id' => $eventId,
            'payload' => json_encode($payload),
            'processed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]]);

        if ($inserted === 0) {
            Log::info("Webhook Twilio déjà traité, ignoré : {$eventId}.");

            return null;
        }

        return $this->applyToWhatsappMessage($messageSid, $status, $payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function applyToWhatsappMessage(string $messageSid, string $status, array $payload): ?WhatsappMessage
    {
        // Sans global scope : à ce stade, aucune organisation n'est encore
        // connue — c'est justement ce que cette requête doit déterminer
        // (même raisonnement que RecordEmailWebhookEvent).
        $whatsappMessage = WhatsappMessage::query()->withoutGlobalScopes()->where('provider_message_id', $messageSid)->first();

        if ($whatsappMessage === null) {
            Log::warning("Webhook Twilio reçu pour un message inconnu : {$messageSid}.");

            return null;
        }

        $this->currentOrganization->set($whatsappMessage->organization_id);

        match ($status) {
            'sent' => $whatsappMessage->update(['status' => WhatsappMessageStatus::Sent]),
            'delivered' => $whatsappMessage->update(['status' => WhatsappMessageStatus::Delivered, 'delivered_at' => now()]),
            'read' => $whatsappMessage->update(['status' => WhatsappMessageStatus::Read, 'read_at' => now()]),
            'failed' => $whatsappMessage->update([
                'status' => WhatsappMessageStatus::Failed,
                'failed_at' => now(),
                'failed_reason' => (string) ($payload['ErrorMessage'] ?? $payload['ErrorCode'] ?? 'Échec inconnu'),
            ]),
            'undelivered' => $whatsappMessage->update([
                'status' => WhatsappMessageStatus::Undelivered,
                'failed_at' => now(),
                'failed_reason' => (string) ($payload['ErrorMessage'] ?? $payload['ErrorCode'] ?? 'Non distribué'),
            ]),
            default => Log::info("Statut Twilio ignoré : {$status}."),
        };

        return $whatsappMessage->fresh();
    }
}
