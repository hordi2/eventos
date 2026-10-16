<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Billets émis dès le paiement reçu (T-055/T-056, même décision produit que
 * DonationReceiptMail — carte, Mobile Money ou encaissement à l'accueil).
 * Ne porte que des valeurs déjà préparées par SendTicketEmail : mis en
 * file, il ne dépend d'aucun modèle cloisonné par organisation.
 */
final class TicketsIssuedMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array{organization: string, event: string, buyerName: string, ticketCount: int}  $context
     * @param  list<array{filename: string, pdf: string}>  $tickets  pdf encodé en base64 (voir SendTicketEmail)
     */
    public function __construct(
        public readonly array $context,
        public readonly array $tickets,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Vos billets — {$this->context['event']}");
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.generic',
            with: [
                'bodyHtml' => view('emails.tickets-issued', ['context' => $this->context])->render(),
                'unsubscribeUrl' => null,
                'organizationLogoUrl' => null,
                'organizationPrimaryColor' => null,
            ],
        );
    }

    /**
     * @return list<Attachment>
     */
    public function attachments(): array
    {
        return array_map(
            fn (array $ticket): Attachment => Attachment::fromData(fn (): string => base64_decode($ticket['pdf']), $ticket['filename'])->withMime('application/pdf'),
            $this->tickets,
        );
    }
}
