<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Relance d'échec de prélèvement (T-074, AC : « relances J+1, J+3, J+7,
 * puis restriction »).
 */
final class PaymentFailedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        private readonly int $daysSinceFailure,
        private readonly bool $isFinalNotice,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->isFinalNotice
                ? 'Paiement échoué : votre abonnement est restreint'
                : "Paiement échoué depuis {$this->daysSinceFailure} jour(s)",
        );
    }

    public function content(): Content
    {
        $message = $this->isFinalNotice
            ? "Le prélèvement de votre abonnement échoue depuis {$this->daysSinceFailure} jours. Votre organisation repasse temporairement sur les quotas du plan gratuit jusqu'à régularisation du paiement — vos données et votre historique restent intacts."
            : "Le prélèvement de votre abonnement a échoué il y a {$this->daysSinceFailure} jour(s). Merci de mettre à jour votre moyen de paiement pour éviter toute interruption de service.";

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
