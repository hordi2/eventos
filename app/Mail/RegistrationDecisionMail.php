<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Décision de l'organisateur sur une inscription qui attendait sa
 * validation (NotifyGuestOfRegistrationDecision). Ne porte que de simples
 * valeurs : mis en file, il ne dépend d'aucun modèle cloisonné par
 * organisation.
 */
final class RegistrationDecisionMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly bool $approved,
        public readonly string $organizationName,
        public readonly string $eventTitle,
        public readonly ?string $reason = null,
        public readonly ?string $confirmationUrl = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->approved
            ? "Votre inscription est confirmée — {$this->eventTitle}"
            : "Votre demande d'inscription — {$this->eventTitle}");
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.generic',
            with: [
                'bodyHtml' => view('emails.registration-decision', [
                    'approved' => $this->approved,
                    'organizationName' => $this->organizationName,
                    'eventTitle' => $this->eventTitle,
                    'reason' => $this->reason,
                    'confirmationUrl' => $this->confirmationUrl,
                ])->render(),
                'unsubscribeUrl' => null,
                'organizationLogoUrl' => null,
                'organizationPrimaryColor' => null,
            ],
        );
    }
}
