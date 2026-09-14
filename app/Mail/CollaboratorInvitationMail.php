<?php

declare(strict_types=1);

namespace App\Mail;

use App\Domain\Organization\Models\Collaborator;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Invitation envoyée depuis Paramètres → Partage d'événements. Ne porte que
 * des chaînes, jamais de modèle Eloquent (même raison que
 * ReferralInvitationMail).
 */
final class CollaboratorInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  list<array{title: string, permission: string}>  $events
     */
    public function __construct(
        public readonly string $inviterName,
        public readonly string $organizationName,
        public readonly array $events,
        public readonly string $invitationUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "{$this->inviterName} vous invite à collaborer sur ses événements");
    }

    public function content(): Content
    {
        $inviter = e($this->inviterName);
        $organization = e($this->organizationName);
        $url = e($this->invitationUrl);
        $days = Collaborator::INVITATION_VALIDITY_DAYS;
        $events = implode('', array_map(
            fn (array $event): string => '<li><strong>'.e($event['title']).'</strong> — '.e($event['permission']).'</li>',
            $this->events,
        ));

        return new Content(
            view: 'emails.generic',
            with: [
                'bodyHtml' => "<p>{$inviter} ({$organization}) vous invite à l'aider à gérer ces événements sur Itaza Invitation :</p>"
                    ."<ul>{$events}</ul>"
                    ."<p>Acceptez l'invitation depuis ce lien, valable {$days} jours :</p>"
                    ."<p><a href=\"{$url}\">{$url}</a></p>",
                'unsubscribeUrl' => null,
                'organizationLogoUrl' => null,
                'organizationPrimaryColor' => null,
            ],
        );
    }
}
