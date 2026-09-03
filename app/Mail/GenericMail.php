<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
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
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->mailSubject);
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.generic',
            with: ['bodyHtml' => $this->bodyHtml, 'unsubscribeUrl' => $this->unsubscribeUrl],
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
