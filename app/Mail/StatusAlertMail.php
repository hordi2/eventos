<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Alerte envoyée à STATUS_ALERT_EMAIL uniquement lors d'un changement
 * d'état (T-076) — jamais à chaque vérification, pour ne pas noyer
 * l'équipe sous les e-mails pendant un incident prolongé.
 */
final class StatusAlertMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array<string, bool>  $failingComponents
     */
    public function __construct(
        private readonly bool $isHealthy,
        private readonly array $failingComponents,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->isHealthy
                ? 'Itaza Invitation : incident résolu'
                : 'Itaza Invitation : incident en cours',
        );
    }

    public function content(): Content
    {
        $message = $this->isHealthy
            ? 'Tous les composants surveillés répondent de nouveau normalement.'
            : 'Composant(s) en échec : '.implode(', ', array_keys($this->failingComponents)).'.';

        return new Content(
            view: 'emails.generic',
            with: [
                'bodyHtml' => '<p>'.e($message).'</p>',
                'unsubscribeUrl' => null,
                'organizationLogoUrl' => null,
                'organizationPrimaryColor' => null,
            ],
        );
    }
}
