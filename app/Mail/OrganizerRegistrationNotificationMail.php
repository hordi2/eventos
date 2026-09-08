<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Paramètres → Notifications (demande utilisateur). Ne porte que des
 * chaînes, jamais de modèle Eloquent : mise en file sans risque de
 * re-résolution prématurée du scope multi-tenant (même piège que
 * SendConfirmationEmail, voir son docblock).
 */
final class OrganizerRegistrationNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        private readonly string $eventTitle,
        private readonly string $guestName,
        private readonly string $type,
    ) {}

    public function envelope(): Envelope
    {
        $subject = match ($this->type) {
            'created' => "Nouvelle inscription — {$this->eventTitle}",
            'updated' => "Inscription modifiée — {$this->eventTitle}",
            'cancelled' => "Inscription annulée — {$this->eventTitle}",
            default => "Inscription — {$this->eventTitle}",
        };

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        $message = match ($this->type) {
            'created' => "{$this->guestName} vient de s'inscrire à « {$this->eventTitle} ».",
            'updated' => "{$this->guestName} a modifié son inscription à « {$this->eventTitle} ».",
            'cancelled' => "{$this->guestName} a annulé son inscription à « {$this->eventTitle} ».",
            default => "Mise à jour d'inscription pour « {$this->eventTitle} ».",
        };

        return new Content(
            view: 'emails.generic',
            with: [
                'bodyHtml' => '<p>'.e($message).'</p><p>Désactivez ces notifications depuis Paramètres → Notifications.</p>',
                'unsubscribeUrl' => null,
                'organizationLogoUrl' => null,
                'organizationPrimaryColor' => null,
            ],
        );
    }
}
