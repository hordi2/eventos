<?php

declare(strict_types=1);

use App\Domain\Event\Models\Event;
use App\Domain\Organization\Models\Organization;
use App\Domain\Ticketing\Actions\GenerateTicketPdf;
use App\Domain\Ticketing\Actions\GenerateTicketQrToken;
use App\Domain\Ticketing\Data\TicketPdfContext;
use App\Domain\Ticketing\Models\Order;
use App\Domain\Ticketing\Models\OrderItem;
use App\Domain\Ticketing\Models\Ticket;
use App\Domain\Ticketing\Models\TicketType;
use App\Support\MultiTenancy\CurrentOrganization;
use Carbon\CarbonImmutable;

it('génère un PDF de billet lisible avec le QR intégré', function (): void {
    config(['services.ticket_qr.secret' => 'test-qr-secret-au-moins-256-bits-pour-hs256']);
    $organization = Organization::factory()->create();
    app(CurrentOrganization::class)->set($organization);

    // Chaîne explicitement liée à la même organisation à chaque niveau,
    // voir TicketQrTokenTest pour le même piège de factories imbriquées.
    $event = Event::factory()->for($organization)->create();
    $ticketType = TicketType::factory()->for($organization)->create(['event_id' => $event->id]);
    $order = Order::factory()->for($organization)->create(['event_id' => $event->id]);
    $orderItem = OrderItem::factory()->for($organization)->create(['order_id' => $order->id, 'ticket_type_id' => $ticketType->id]);
    $ticket = Ticket::factory()->for($organization)->create(['order_item_id' => $orderItem->id, 'ticket_type_id' => $ticketType->id]);

    $token = app(GenerateTicketQrToken::class)->handle($ticket, CarbonImmutable::now()->addDay());

    $context = new TicketPdfContext(
        organizationName: 'Itaza Démo',
        eventTitle: 'Conférence Itaza 2026',
        eventStartAt: CarbonImmutable::parse('2026-11-15 18:00:00'),
        eventTimezone: 'Africa/Kinshasa',
        venueName: 'Palais du Peuple',
        ticketTypeName: 'Billet standard',
        buyerName: 'Alice Kouassi',
    );

    $pdf = app(GenerateTicketPdf::class)->handle($ticket, $token, $context);

    expect($pdf)->toStartWith('%PDF-');
    expect(strlen($pdf))->toBeGreaterThan(1000);
});
