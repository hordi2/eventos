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
    public function handle(array $payload, int $organizationId): ?WhatsappMessage
    {
        $messageSid = (string) ($payload['MessageSid'] ?? $payload['SmsSid'] ?? '');
        $status = (string) ($payload['MessageStatus'] ?? '');
        $eventId = "{$messageSid}:{$status}";

        // Insertion et traitement dans une seule transaction (même
        // raisonnement que RecordStripeWebhookEvent, T-052) : une exception
        // imprévue dans applyToWhatsappMessage() ne doit jamais marquer
        // l'événement traité sans l'avoir réellement appliqué.
        //
        // insertOrIgnore() plutôt qu'upsert() avec un $update vide : voir
        // ApplyTagToContacts pour ce piège (Builder::upsert() retombe sur
        // un insert() nu qui échouerait sur ce même conflit).
        return DB::transaction(function () use ($messageSid, $status, $payload, $eventId, $organizationId): ?WhatsappMessage {
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

            return $this->applyToWhatsappMessage($messageSid, $status, $payload, $organizationId);
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function applyToWhatsappMessage(string $messageSid, string $status, array $payload, int $organizationId): ?WhatsappMessage
    {
        // organization_id vient de l'URL de rappel posée à l'envoi
        // (SendWhatsappMessageJob), jamais d'une relecture du message : la
        // row-level security PostgreSQL (règle 4.1 du CLAUDE.md)
        // bloquerait cette relecture tant que le contexte n'est pas posé —
        // le rôle applicatif n'a jamais BYPASSRLS (docker/postgres/init.sql).
        $this->currentOrganization->set($organizationId);

        $whatsappMessage = WhatsappMessage::query()->where('provider_message_id', $messageSid)->first();

        if ($whatsappMessage === null) {
            Log::warning("Webhook Twilio reçu pour un message inconnu : {$messageSid}.");

            return null;
        }

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
