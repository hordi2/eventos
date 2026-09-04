<?php

declare(strict_types=1);

use App\Domain\Organization\Models\MembershipRole;
use App\Domain\Ticketing\Models\Order;
use App\Domain\Ticketing\Models\OrderStatus;
use App\Domain\Ticketing\Models\PriceTier;
use App\Domain\Ticketing\Models\TicketType;
use App\Support\Money;
use App\Support\MultiTenancy\CurrentOrganization;

it('inscrit un invité sur place avec un billet gratuit et le check-in immédiatement', function (): void {
    ['organization' => $organization, 'event' => $event, 'doorStaff' => $doorStaff] = makeCheckInEvent();
    app(CurrentOrganization::class)->set($organization);
    $ticketType = TicketType::factory()->for($organization)->free()->create(['event_id' => $event->id]);
    app(CurrentOrganization::class)->clear();

    $response = $this->actingAs($doorStaff)->postJson("/events/{$event->id}/check-in/walk-in", [
        'ticket_type_id' => $ticketType->id,
        'name' => 'Chris Walk-in',
        'email' => 'chris@example.test',
    ]);

    $response->assertOk();
    $response->assertJsonPath('status', 'accepted');
    $response->assertJsonPath('guest.name', 'Chris Walk-in');
    $response->assertJsonPath('guest.checked_in', true);

    app(CurrentOrganization::class)->set($organization);
    $order = Order::query()->where('buyer_email', 'chris@example.test')->firstOrFail();
    expect($order->status)->toBe(OrderStatus::Paid);
    expect($order->total->isZero())->toBeTrue();
    app(CurrentOrganization::class)->clear();
});

it('inscrit un invité sur place avec un billet payant, encaisse et check-in immédiatement', function (): void {
    ['organization' => $organization, 'event' => $event, 'doorStaff' => $doorStaff] = makeCheckInEvent();
    app(CurrentOrganization::class)->set($organization);
    $ticketType = TicketType::factory()->for($organization)->create(['event_id' => $event->id]);
    PriceTier::factory()->for($ticketType)->for($organization)->create(['amount' => Money::fromMinorUnits(1500, 'EUR')]);
    app(CurrentOrganization::class)->clear();

    $response = $this->actingAs($doorStaff)->postJson("/events/{$event->id}/check-in/walk-in", [
        'ticket_type_id' => $ticketType->id,
        'name' => 'Dana Walk-in',
        'email' => 'dana@example.test',
    ]);

    $response->assertOk();
    $response->assertJsonPath('status', 'accepted');

    app(CurrentOrganization::class)->set($organization);
    $order = Order::query()->where('buyer_email', 'dana@example.test')->firstOrFail();
    expect($order->status)->toBe(OrderStatus::Paid);
    expect($order->total->amountMinor())->toBe(1500);
    expect($order->payments->first()->provider)->toBe('cash');
    app(CurrentOrganization::class)->clear();
});

it('refuse un billet dont le quota est épuisé', function (): void {
    ['organization' => $organization, 'event' => $event, 'doorStaff' => $doorStaff] = makeCheckInEvent();
    app(CurrentOrganization::class)->set($organization);
    $ticketType = TicketType::factory()->for($organization)->free()->create(['event_id' => $event->id, 'total_quantity' => 0]);
    app(CurrentOrganization::class)->clear();

    $response = $this->actingAs($doorStaff)->postJson("/events/{$event->id}/check-in/walk-in", [
        'ticket_type_id' => $ticketType->id,
        'name' => 'Trop Tard',
        'email' => 'trop-tard@example.test',
    ]);

    $response->assertUnprocessable();
});

it('refuse l\'ajout d\'un invité sur place à un rôle sans la capacité checkIn', function (): void {
    ['organization' => $organization, 'event' => $event, 'doorStaff' => $viewer] = makeCheckInEvent(MembershipRole::Viewer);
    app(CurrentOrganization::class)->set($organization);
    $ticketType = TicketType::factory()->for($organization)->free()->create(['event_id' => $event->id]);
    app(CurrentOrganization::class)->clear();

    $response = $this->actingAs($viewer)->postJson("/events/{$event->id}/check-in/walk-in", [
        'ticket_type_id' => $ticketType->id,
        'name' => 'Sans Droit',
        'email' => 'sans-droit@example.test',
    ]);

    $response->assertForbidden();
});
