<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Lien du portail envoyé à un intervenant (D6). Ne porte que de simples
 * valeurs : mis en file, il ne dépend d'aucun modèle cloisonné par
 * organisation.
 */
final class SpeakerPortalInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $speakerName,
        public readonly string $organizationName,
        public readonly string $eventTitle,
        public readonly string $eventSchedule,
        public readonly string $portalUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Votre espace intervenant — {$this->eventTitle}");
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.generic',
            with: [
                'bodyHtml' => view('emails.speaker-portal-invitation', [
                    'speakerName' => $this->speakerName,
                    'organizationName' => $this->organizationName,
                    'eventTitle' => $this->eventTitle,
                    'eventSchedule' => $this->eventSchedule,
                    'portalUrl' => $this->portalUrl,
                ])->render(),
                'unsubscribeUrl' => null,
                'organizationLogoUrl' => null,
                'organizationPrimaryColor' => null,
            ],
        );
    }
}
