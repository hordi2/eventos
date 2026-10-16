<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Invitation envoyée depuis Paramètres → Équipe. Ne porte que des chaînes,
 * jamais de modèle Eloquent (même raison que CollaboratorInvitationMail).
 */
final class TeamMemberInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $inviterName,
        public readonly string $organizationName,
        public readonly string $roleLabel,
        public readonly string $invitationUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "{$this->inviterName} vous invite à rejoindre {$this->organizationName}");
    }

    public function content(): Content
    {
        $inviter = e($this->inviterName);
        $organization = e($this->organizationName);
        $role = e($this->roleLabel);
        $url = e($this->invitationUrl);

        return new Content(
            view: 'emails.generic',
            with: [
                'bodyHtml' => "<p>{$inviter} vous invite à rejoindre <strong>{$organization}</strong> sur Itaza Invitation, avec le rôle <strong>{$role}</strong>.</p>"
                    ."<p>Acceptez l'invitation depuis ce lien, valable 7 jours :</p>"
                    ."<p><a href=\"{$url}\">{$url}</a></p>",
                'unsubscribeUrl' => null,
                'organizationLogoUrl' => null,
                'organizationPrimaryColor' => null,
            ],
        );
    }
}
