<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Domain\Contact\Models\Contact;
use App\Domain\Event\Models\Event;
use App\Domain\Messaging\Models\FollowUpMessage;
use App\Domain\Organization\Models\Organization;
use App\Domain\Ticketing\Actions\GenerateTicketPdf;
use App\Domain\Ticketing\Actions\GenerateTicketQrToken;
use App\Domain\Ticketing\Data\TicketPdfContext;
use App\Domain\Ticketing\Events\OrderPaid;
use App\Domain\Ticketing\Models\Order;
use App\Domain\Ticketing\Models\OrderItem;
use App\Domain\Ticketing\Models\Ticket;
use App\Mail\TicketsIssuedMail;
use App\Support\Messaging\SendFollowUpMessage;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Mail;

/**
 * Billets envoyés par e-mail dès que le paiement est reçu (T-055/T-056,
 * même décision produit que SendDonationReceipt : carte, Mobile Money ou
 * encaissement à l'accueil) — jusqu'ici, seul un téléchargement à la
 * demande depuis la page de confirmation existait (TicketOrderPaymentController),
 * et un invité qui ne la revisite jamais perdait son billet.
 *
 * Traverse Ticketing, Event et Organization, d'où sa place hors de Domain,
 * comme SendDonationReceipt. Volontairement synchrone pour la même raison
 * (modèle cloisonné porté par l'événement) : seul l'envoi part en file.
 */
final class SendTicketEmail
{
    public function __construct(
        private readonly GenerateTicketQrToken $generateTicketQrToken,
        private readonly GenerateTicketPdf $generateTicketPdf,
        private readonly SendFollowUpMessage $sendFollowUpMessage,
    ) {}

    public function handle(OrderPaid $orderPaid): void
    {
        $order = $orderPaid->order->load(['items.tickets.ticketType']);
        $tickets = $order->items->flatMap(fn (OrderItem $item) => $item->tickets);

        if ($tickets->isEmpty()) {
            return;
        }

        $event = Event::query()->with('venue')->findOrFail($order->event_id);
        $organization = Organization::query()->findOrFail($order->organization_id);

        $this->sendFollowUpMessage->sendWhatsapp($organization, $this->contact($order), FollowUpMessage::TicketIssued, $event);

        if ($order->buyer_email === '' || ! $this->sendFollowUpMessage->sendsEmail($organization)) {
            return;
        }

        $expiresAt = CarbonImmutable::instance($event->end_at ?? $event->start_at)->addDay();

        // encodé en base64 : un Mailable mis en file est sérialisé puis
        // ré-encodé en JSON par le système de queue (Illuminate\Queue\Queue),
        // qui échoue sur des octets PDF bruts (UTF-8 invalide) — TicketsIssuedMail
        // décode au moment de construire les pièces jointes.
        /** @var list<array{filename: string, pdf: string}> $attachments */
        $attachments = $tickets->map(function (Ticket $ticket) use ($event, $organization, $order, $expiresAt): array {
            $qrToken = $this->generateTicketQrToken->handle($ticket, $expiresAt);

            $pdf = $this->generateTicketPdf->handle($ticket, $qrToken, new TicketPdfContext(
                organizationName: $organization->name,
                eventTitle: $event->title,
                eventStartAt: $event->start_at,
                eventTimezone: $event->timezone,
                venueName: $event->venue?->name,
                ticketTypeName: $ticket->ticketType->name,
                buyerName: $order->buyer_name,
            ));

            return ['filename' => "billet-{$ticket->id}.pdf", 'pdf' => base64_encode($pdf)];
        })->values()->all();

        Mail::to($order->buyer_email)->queue(new TicketsIssuedMail([
            'organization' => $organization->name,
            'event' => $event->title,
            'buyerName' => $order->buyer_name,
            'ticketCount' => count($attachments),
        ], $attachments));
    }

    private function contact(Order $order): ?Contact
    {
        return $order->contact_id === null ? null : Contact::query()->find($order->contact_id);
    }
}
