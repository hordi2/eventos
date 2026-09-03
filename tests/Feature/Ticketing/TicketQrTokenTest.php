<?php

declare(strict_types=1);

use App\Domain\Event\Models\Event;
use App\Domain\Organization\Models\Organization;
use App\Domain\Ticketing\Actions\GenerateTicketQrToken;
use App\Domain\Ticketing\Actions\ReissueTicketQr;
use App\Domain\Ticketing\Actions\VerifyTicketQrToken;
use App\Domain\Ticketing\InvalidQrTokenException;
use App\Domain\Ticketing\Models\Order;
use App\Domain\Ticketing\Models\OrderItem;
use App\Domain\Ticketing\Models\Ticket;
use App\Domain\Ticketing\Models\TicketStatus;
use App\Domain\Ticketing\Models\TicketType;
use App\Support\MultiTenancy\CurrentOrganization;
use Carbon\CarbonImmutable;

beforeEach(function (): void {
    config(['services.ticket_qr.secret' => 'test-qr-secret-au-moins-256-bits-pour-hs256']);
    $this->organization = Organization::factory()->create();
    app(CurrentOrganization::class)->set($this->organization);

    // Chaîne explicitement liée à la même organisation à chaque niveau :
    // les factories imbriquées (OrderItem -> TicketType -> Event) en
    // créeraient chacune une nouvelle sans ça, rejetée par la RLS.
    $event = Event::factory()->for($this->organization)->create();
    $ticketType = TicketType::factory()->for($this->organization)->create(['event_id' => $event->id]);
    $order = Order::factory()->for($this->organization)->create(['event_id' => $event->id]);
    $orderItem = OrderItem::factory()->for($this->organization)->create(['order_id' => $order->id, 'ticket_type_id' => $ticketType->id]);
    $this->ticket = Ticket::factory()->for($this->organization)->create(['order_item_id' => $orderItem->id, 'ticket_type_id' => $ticketType->id]);
});

it('génère un JWT et lui attribue un jti persistant', function (): void {
    $token = app(GenerateTicketQrToken::class)->handle($this->ticket, CarbonImmutable::now()->addDay());

    expect(substr_count($token, '.'))->toBe(2);
    expect($this->ticket->fresh()->qr_jti)->not->toBeNull();
});

it('réutilise le même jti sur des appels successifs sans réémission', function (): void {
    app(GenerateTicketQrToken::class)->handle($this->ticket, CarbonImmutable::now()->addDay());
    $firstJti = $this->ticket->fresh()->qr_jti;

    app(GenerateTicketQrToken::class)->handle($this->ticket->fresh(), CarbonImmutable::now()->addDay());

    expect($this->ticket->fresh()->qr_jti)->toBe($firstJti);
});

it('vérifie un jeton valide et retrouve le bon billet', function (): void {
    $token = app(GenerateTicketQrToken::class)->handle($this->ticket, CarbonImmutable::now()->addDay());

    $verified = app(VerifyTicketQrToken::class)->handle($token);

    expect($verified->id)->toBe($this->ticket->id);
});

it('rejette un jeton dont la signature a été altérée', function (): void {
    $token = app(GenerateTicketQrToken::class)->handle($this->ticket, CarbonImmutable::now()->addDay());

    app(VerifyTicketQrToken::class)->handle($token.'tampered');
})->throws(InvalidQrTokenException::class);

it('rejette un jeton expiré', function (): void {
    $token = app(GenerateTicketQrToken::class)->handle($this->ticket, CarbonImmutable::now()->subMinute());

    app(VerifyTicketQrToken::class)->handle($token);
})->throws(InvalidQrTokenException::class);

it('rejette un jeton mal formé', function (): void {
    app(VerifyTicketQrToken::class)->handle('pas-un-jwt');
})->throws(InvalidQrTokenException::class);

it('rejette un jeton pour un billet annulé', function (): void {
    $token = app(GenerateTicketQrToken::class)->handle($this->ticket, CarbonImmutable::now()->addDay());
    $this->ticket->update(['status' => TicketStatus::Cancelled]);

    app(VerifyTicketQrToken::class)->handle($token);
})->throws(InvalidQrTokenException::class);

it('réémet le billet et invalide l\'ancien jeton', function (): void {
    $oldToken = app(GenerateTicketQrToken::class)->handle($this->ticket, CarbonImmutable::now()->addDay());
    $oldJti = $this->ticket->fresh()->qr_jti;

    $reissued = app(ReissueTicketQr::class)->handle($this->ticket->fresh());
    $newToken = app(GenerateTicketQrToken::class)->handle($reissued, CarbonImmutable::now()->addDay());

    expect($reissued->qr_jti)->not->toBe($oldJti);
    expect(app(VerifyTicketQrToken::class)->handle($newToken)->id)->toBe($this->ticket->id);

    app(VerifyTicketQrToken::class)->handle($oldToken);
})->throws(InvalidQrTokenException::class);

it('refuse de réémettre un billet annulé', function (): void {
    $this->ticket->update(['status' => TicketStatus::Cancelled]);

    app(ReissueTicketQr::class)->handle($this->ticket);
})->throws(InvalidQrTokenException::class);
