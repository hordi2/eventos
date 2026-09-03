<?php

declare(strict_types=1);

namespace App\Domain\Messaging\Actions;

use App\Domain\Messaging\Models\EmailMessage;
use App\Domain\Messaging\Models\EmailMessageStatus;
use App\Support\MultiTenancy\CurrentOrganization;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Traite un événement webhook Postmark de façon idempotente (règle 4.4 du
 * CLAUDE.md) : un identifiant stable est calculé à partir de la charge
 * utile et enregistré une seule fois (contrainte unique provider+event_id),
 * un doublon est ignoré silencieusement (log en info, jamais une erreur).
 *
 * Ne connaît pas Contact (Domain/Contact) : la mise à jour du contact sur
 * bounce dur / plainte est laissée à l'appelant (PostmarkWebhookController),
 * seul endroit autorisé à traverser les deux modules — voir le docblock de
 * Contact::class.
 */
final class RecordEmailWebhookEvent
{
    public function __construct(
        private readonly CurrentOrganization $currentOrganization,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(array $payload): ?EmailMessage
    {
        $eventId = $this->eventId($payload);

        // insertOrIgnore() (ON CONFLICT DO NOTHING sur Postgres) renvoie le
        // nombre de lignes réellement écrites : 0 si la contrainte unique
        // (provider, event_id) a bloqué l'insertion, donc si cet événement
        // a déjà été traité. upsert() ne conviendrait pas ici : avec un
        // $update vide, Laravel retombe sur un insert() nu qui échouerait
        // sur ce même conflit (voir ApplyTagToContacts pour ce piège).
        $inserted = DB::table('email_webhook_events')->insertOrIgnore([[
            'provider' => 'postmark',
            'event_id' => $eventId,
            'payload' => json_encode($payload),
            'processed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]]);

        if ($inserted === 0) {
            Log::info("Webhook Postmark déjà traité, ignoré : {$eventId}.");

            return null;
        }

        return $this->applyToEmailMessage($payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function eventId(array $payload): string
    {
        $recordType = (string) ($payload['RecordType'] ?? 'unknown');
        $messageId = (string) ($payload['MessageID'] ?? 'unknown');

        // La plupart des types Postmark n'ont pas d'identifiant propre :
        // le couple (type, message, horodatage fourni par Postmark) est le
        // meilleur identifiant stable disponible pour ce type d'événement.
        $occurredAt = (string) ($payload['BouncedAt'] ?? $payload['DeliveredAt'] ?? $payload['ReceivedAt'] ?? $payload['ChangedAt'] ?? '');

        return "{$recordType}:{$messageId}:{$occurredAt}";
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function applyToEmailMessage(array $payload): ?EmailMessage
    {
        $messageId = (string) ($payload['MessageID'] ?? '');

        // Sans global scope : à ce stade, aucune organisation n'est encore
        // connue (le webhook est public, sans middleware resolve-organization)
        // — c'est justement ce que cette requête doit déterminer.
        $emailMessage = EmailMessage::query()->withoutGlobalScopes()->where('provider_message_id', $messageId)->first();

        if ($emailMessage === null) {
            Log::warning("Webhook Postmark reçu pour un message inconnu : {$messageId}.");

            return null;
        }

        // Organisation résolue : le contexte peut être positionné pour la
        // suite (y compris pour l'appelant, qui doit pouvoir toucher au
        // contact lié sans devoir le refaire lui-même).
        $this->currentOrganization->set($emailMessage->organization_id);

        match ($payload['RecordType'] ?? null) {
            'Delivery' => $emailMessage->update(['status' => EmailMessageStatus::Delivered, 'delivered_at' => now()]),
            'Bounce' => $this->applyBounce($emailMessage, $payload),
            'SpamComplaint' => $emailMessage->update(['status' => EmailMessageStatus::Complained, 'complained_at' => now()]),
            'Open' => $emailMessage->opened_at === null ? $emailMessage->update(['opened_at' => now()]) : null,
            'Click' => $emailMessage->first_clicked_at === null ? $emailMessage->update(['first_clicked_at' => now()]) : null,
            default => Log::info("Type d'événement Postmark ignoré : ".($payload['RecordType'] ?? 'inconnu')),
        };

        return $emailMessage->fresh();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function applyBounce(EmailMessage $emailMessage, array $payload): void
    {
        // Postmark distingue de nombreux types précis (HardBounce,
        // SoftBounce, Transient...) ; seul un bounce dur exclut le contact
        // des envois futurs (règle du ticket), un bounce transitoire ne
        // change que le statut du message.
        $isHard = ($payload['Type'] ?? null) === 'HardBounce';

        $emailMessage->update([
            'status' => EmailMessageStatus::Bounced,
            'bounced_at' => now(),
            'bounce_type' => $isHard ? 'hard' : 'soft',
        ]);
    }
}
