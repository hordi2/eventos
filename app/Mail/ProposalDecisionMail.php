<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Décision de l'organisateur sur un sujet proposé (D6). Le motif n'est
 * transmis que si l'organisateur l'a écrit pour le proposant.
 */
final class ProposalDecisionMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly bool $accepted,
        public readonly string $proposerName,
        public readonly string $organizationName,
        public readonly string $eventTitle,
        public readonly string $proposalTitle,
        public readonly ?string $note = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->accepted
            ? "Votre sujet est retenu — {$this->eventTitle}"
            : "Votre proposition — {$this->eventTitle}");
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.generic',
            with: [
                'bodyHtml' => view('emails.proposal-decision', [
                    'accepted' => $this->accepted,
                    'proposerName' => $this->proposerName,
                    'organizationName' => $this->organizationName,
                    'eventTitle' => $this->eventTitle,
                    'proposalTitle' => $this->proposalTitle,
                    'note' => $this->note,
                ])->render(),
                'unsubscribeUrl' => null,
                'organizationLogoUrl' => null,
                'organizationPrimaryColor' => null,
            ],
        );
    }
}
