<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\Messaging\Models\EmailMessage;
use App\Domain\Messaging\Models\EmailMessageStatus;
use App\Mail\GenericMail;
use App\Support\MultiTenancy\CurrentOrganization;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Envoie un e-mail déjà journalisé (EmailMessage) via le mailer configuré
 * (Postmark en production). Reçoit un ID, jamais le modèle : même piège que
 * ProcessContactImportJob (T-041) — SerializesModels re-résoudrait
 * EmailMessage via son global scope avant que CurrentOrganization ne soit
 * positionné.
 *
 * La limitation de débit (T-043 : « sans dépasser le débit autorisé ») est
 * portée par le middleware de queue RateLimited, pas par une boucle
 * artificielle ici — c'est le mécanisme prévu par Laravel pour ça.
 */
final class SendEmailMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public readonly int $emailMessageId,
        private readonly int $organizationId,
        private readonly string $bodyHtml,
        private readonly ?string $unsubscribeUrl,
        private readonly ?string $icsAttachment = null,
    ) {}

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [new RateLimited('email-sends')];
    }

    public function handle(CurrentOrganization $currentOrganization): void
    {
        $currentOrganization->set($this->organizationId);

        $emailMessage = EmailMessage::query()->findOrFail($this->emailMessageId);

        try {
            $sent = Mail::to($emailMessage->to_email)->send(new GenericMail(
                $emailMessage->subject,
                $this->bodyHtml,
                $this->unsubscribeUrl,
                $this->icsAttachment,
            ));

            $emailMessage->update([
                'status' => EmailMessageStatus::Sent,
                'provider_message_id' => $sent?->getMessageId(),
                'sent_at' => now(),
            ]);
        } catch (Throwable $e) {
            $emailMessage->update([
                'status' => EmailMessageStatus::Failed,
                'failed_reason' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
