<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Reçu de don (T-056). Ne porte que des valeurs déjà mises en forme par
 * SendDonationReceipt : mis en file, il ne dépend d'aucun modèle cloisonné
 * par organisation.
 */
final class DonationReceiptMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array{number: string, organization: string, event: string, amount: string, paidAt: string, method: string, cause: ?string, donorName: string, donorCompany: ?string, donorAddress: string, anonymous: bool}  $receipt
     */
    public function __construct(
        public readonly array $receipt,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Reçu de votre don — {$this->receipt['event']}");
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.generic',
            with: [
                'bodyHtml' => view('emails.donation-receipt', ['receipt' => $this->receipt])->render(),
                'unsubscribeUrl' => null,
                'organizationLogoUrl' => null,
                'organizationPrimaryColor' => null,
            ],
        );
    }
}
