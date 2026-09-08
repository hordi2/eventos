<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Invitation de parrainage envoyée depuis Paramètres → Refer-a-Friend. Ne
 * porte que des chaînes, jamais de modèle Eloquent (même raison que
 * OrganizerRegistrationNotificationMail).
 */
final class ReferralInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        private readonly string $inviterName,
        private readonly string $referralUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "{$this->inviterName} vous invite à essayer Itaza Invitation");
    }

    public function content(): Content
    {
        $inviter = e($this->inviterName);
        $url = e($this->referralUrl);

        return new Content(
            view: 'emails.generic',
            with: [
                'bodyHtml' => "<p>{$inviter} utilise Itaza Invitation pour organiser ses événements et vous invite à l'essayer.</p>"
                    .'<p>En créant votre compte depuis ce lien puis en passant à un forfait payant, vous recevez chacun un mois offert :</p>'
                    ."<p><a href=\"{$url}\">{$url}</a></p>",
                'unsubscribeUrl' => null,
                'organizationLogoUrl' => null,
                'organizationPrimaryColor' => null,
            ],
        );
    }
}
