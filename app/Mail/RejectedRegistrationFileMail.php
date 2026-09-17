<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Fichier joint refusé par l'antivirus (NotifyGuestOfRejectedFile). Ne porte
 * que de simples valeurs : mis en file, il ne dépend d'aucun modèle cloisonné
 * par organisation.
 */
final class RejectedRegistrationFileMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $organizationName,
        public readonly string $eventTitle,
        public readonly string $fileName,
        public readonly string $question,
        public readonly ?string $editUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Votre fichier n'a pas pu être accepté — {$this->eventTitle}");
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.generic',
            with: [
                'bodyHtml' => view('emails.rejected-registration-file', [
                    'organizationName' => $this->organizationName,
                    'eventTitle' => $this->eventTitle,
                    'fileName' => $this->fileName,
                    'question' => $this->question,
                    'editUrl' => $this->editUrl,
                ])->render(),
                'unsubscribeUrl' => null,
                'organizationLogoUrl' => null,
                'organizationPrimaryColor' => null,
            ],
        );
    }
}
