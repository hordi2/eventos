<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Accusé de réception d'un sujet proposé (D6). Ne porte que de simples
 * valeurs : mis en file, il ne dépend d'aucun modèle cloisonné par
 * organisation.
 */
final class ProposalReceivedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $proposerName,
        public readonly string $organizationName,
        public readonly string $eventTitle,
        public readonly string $proposalTitle,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Votre proposition est bien arrivée — {$this->eventTitle}");
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.generic',
            with: [
                'bodyHtml' => view('emails.proposal-received', [
                    'proposerName' => $this->proposerName,
                    'organizationName' => $this->organizationName,
                    'eventTitle' => $this->eventTitle,
                    'proposalTitle' => $this->proposalTitle,
                ])->render(),
                'unsubscribeUrl' => null,
                'organizationLogoUrl' => null,
                'organizationPrimaryColor' => null,
            ],
        );
    }
}
