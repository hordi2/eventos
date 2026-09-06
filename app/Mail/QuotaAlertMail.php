<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Alerte de quota (T-074, AC : « alerte à 80 % et 100 % de quota »).
 * Réutilise le même gabarit que GenericMail (logo/couleur de
 * l'organisation) pour rester visuellement cohérent avec les autres
 * e-mails envoyés par l'organisation elle-même — celui-ci vient d'Itaza,
 * pas d'elle, mais le destinataire est l'organisateur, pas un invité.
 */
final class QuotaAlertMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        private readonly string $metricLabel,
        private readonly int $threshold,
        private readonly int $used,
        private readonly int $quota,
    ) {}

    public function envelope(): Envelope
    {
        $subject = $this->threshold >= 100
            ? "Quota atteint : {$this->metricLabel}"
            : "Quota bientôt atteint : {$this->metricLabel}";

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        $message = $this->threshold >= 100
            ? "Vous avez atteint 100 % de votre quota mensuel de {$this->metricLabel} ({$this->used}/{$this->quota}). Les nouvelles inscriptions passent en liste d'attente jusqu'au mois prochain, ou passez à un plan supérieur pour lever cette limite dès maintenant."
            : "Vous avez atteint {$this->threshold} % de votre quota mensuel de {$this->metricLabel} ({$this->used}/{$this->quota}).";

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
