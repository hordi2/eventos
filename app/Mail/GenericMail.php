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
        public readonly ?string $icsAttachment = null,
        public readonly ?string $organizationLogoUrl = null,
        public readonly ?string $organizationPrimaryColor = null,
        public readonly ?string $fromName = null,
        public readonly ?string $replyToAddress = null,
    ) {}

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
