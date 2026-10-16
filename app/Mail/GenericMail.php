<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Symfony\Component\Mailer\Header\MetadataHeader;
use Symfony\Component\Mime\Email;

/**
 * Enveloppe générique pour tout e-mail envoyé par SendEmail (T-043) — pas
 * de ShouldQueue ici volontairement : c'est SendEmailMessageJob qui porte
 * la mise en file et la limitation de débit, cette classe ne fait
 * qu'assembler le message au moment où le job l'envoie.
 */
final class GenericMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $mailSubject,
        public readonly string $bodyHtml,
        public readonly ?string $unsubscribeUrl,
        public readonly int $organizationId,
        public readonly ?string $icsAttachment = null,
        public readonly ?string $organizationLogoUrl = null,
        public readonly ?string $organizationPrimaryColor = null,
        public readonly ?string $fromName = null,
        public readonly ?string $replyToAddress = null,
    ) {}

    /**
     * Le webhook Postmark (RecordEmailWebhookEvent) a besoin de
     * l'organisation pour poser le contexte multi-tenant avant de relire
     * quoi que ce soit — EmailMessage est protégé par la row-level
     * security (règle 4.1 du CLAUDE.md), et le rôle applicatif n'a jamais
     * BYPASSRLS (docker/postgres/init.sql). Metadata est le mécanisme
     * Postmark prévu pour ça : repris tel quel dans tous ses webhooks.
     */
    public function build(): void
    {
        $this->withSymfonyMessage(function (Email $message): void {
            $message->getHeaders()->add(new MetadataHeader('organization_id', (string) $this->organizationId));
        });
    }

    public function envelope(): Envelope
    {
        // Étiquetage blanc (Paramètres → Étiquetage blanc) : seul le nom
        // affiché change, jamais l'adresse technique d'expédition
        // (MAIL_FROM_ADDRESS) — la modifier sans vérification de domaine
        // SPF/DKIM dégraderait la délivrabilité plutôt que de l'améliorer.
        return new Envelope(
            from: $this->fromName !== null ? new Address(config('mail.from.address'), $this->fromName) : null,
            replyTo: $this->replyToAddress !== null ? [new Address($this->replyToAddress)] : [],
            subject: $this->mailSubject,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.generic',
            with: [
                'bodyHtml' => $this->bodyHtml,
                'unsubscribeUrl' => $this->unsubscribeUrl,
                'organizationLogoUrl' => $this->organizationLogoUrl,
                'organizationPrimaryColor' => $this->organizationPrimaryColor,
            ],
        );
    }

    /**
     * @return list<Attachment>
     */
    public function attachments(): array
    {
        if ($this->icsAttachment === null) {
            return [];
        }

        return [
            Attachment::fromData(fn (): string => $this->icsAttachment, 'invitation.ics')
                ->withMime('text/calendar'),
        ];
    }
}
