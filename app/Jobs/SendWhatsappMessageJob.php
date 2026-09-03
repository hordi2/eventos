<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\Messaging\Models\WhatsappMessage;
use App\Domain\Messaging\Models\WhatsappMessageStatus;
use App\Support\Messaging\WhatsappProvider;
use App\Support\MultiTenancy\CurrentOrganization;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Envoie un message WhatsApp déjà journalisé (WhatsappMessage) via l'API
 * Content du prestataire (WhatsappProvider — Twilio en production) —
 * jamais de contenu libre, seulement un modèle déjà approuvé (contentSid)
 * et ses variables. Reçoit un ID, jamais le modèle : même piège que
 * SendEmailMessageJob (T-041/043) — SerializesModels re-résoudrait
 * WhatsappMessage via son global scope avant que CurrentOrganization ne
 * soit repositionné.
 *
 * Débit limité par le même mécanisme que l'e-mail (règle 4.4/§5 du
 * CLAUDE.md : tout traitement de masse en queue, débit maîtrisé), sur un
 * limiteur dédié — le forfait WhatsApp de Twilio n'a aucune raison de
 * partager celui de Postmark.
 */
final class SendWhatsappMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /**
     * @param  array<int, string>  $contentVariables
     */
    public function __construct(
        public readonly int $whatsappMessageId,
        private readonly int $organizationId,
        private readonly string $providerTemplateSid,
        private readonly array $contentVariables,
    ) {}

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [new RateLimited('whatsapp-sends')];
    }

    public function handle(CurrentOrganization $currentOrganization, WhatsappProvider $provider): void
    {
        $currentOrganization->set($this->organizationId);

        $whatsappMessage = WhatsappMessage::query()->findOrFail($this->whatsappMessageId);

        try {
            $providerMessageId = $provider->send(
                $whatsappMessage->to_phone_e164,
                $this->providerTemplateSid,
                $this->contentVariables,
                route('webhooks.twilio-whatsapp'),
            );

            $whatsappMessage->update([
                'status' => WhatsappMessageStatus::Sent,
                'provider_message_id' => $providerMessageId,
                'sent_at' => now(),
            ]);
        } catch (Throwable $e) {
            $whatsappMessage->update([
                'status' => WhatsappMessageStatus::Failed,
                'failed_at' => now(),
                'failed_reason' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
