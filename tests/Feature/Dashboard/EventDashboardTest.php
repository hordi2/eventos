<?php

declare(strict_types=1);

use App\Domain\CheckIn\Models\CheckIn;
use App\Domain\Organization\Models\MembershipRole;
use App\Domain\Ticketing\Models\Order;
use App\Domain\Ticketing\Models\OrderItem;
use App\Domain\Ticketing\Models\OrderStatus;
use App\Domain\Ticketing\Models\PriceTier;
use App\Domain\Ticketing\Models\Ticket;
use App\Domain\Ticketing\Models\TicketStatus;
use App\Domain\Ticketing\Models\TicketType;
use App\Support\Dashboard\GetEventDashboardStats;
use App\Support\Money;
use App\Support\MultiTenancy\CurrentOrganization;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

it('calcule les statistiques du tableau de bord (invités confirmés, présents, courbes)', function (): void {
    ['organization' => $organization, 'event' => $event] = makeCheckInEvent(MembershipRole::Owner);
    makeCheckedInAttendee($organization, $event);
    $ticket = makePaidTicket($organization, $event);

    app(CurrentOrganization::class)->set($organization);

    // Un des deux invités est enregistré (check-in accepté), l'autre non.
    CheckIn::query()->create([
        'organization_id' => $organization->id,
        'event_id' => $event->id,
        'ticket_id' => $ticket->id,
        'device_local_id' => (string) Str::uuid(),
        'direction' => 'check_in',
        'status' => 'accepted',
        'recorded_at' => CarbonImmutable::now(),
    ]);

    $stats = app(GetEventDashboardStats::class)->handle($event->fresh());

    app(CurrentOrganization::class)->clear();

    expect($stats->confirmedCount)->toBe(2);
    expect($stats->presentCount)->toBe(1);
    expect($stats->presenceRate)->toBe(0.5);
    expect($stats->registrationCurve)->not->toBeEmpty();
    expect($stats->arrivalCurve)->toHaveCount(1);
    expect($stats->arrivalCurve[0]['count'])->toBe(1);
});

it('la courbe cumulée grandit de façon monotone au fil des jours', function (): void {
    ['organization' => $organization, 'event' => $event] = makeCheckInEvent(MembershipRole::Owner);
    app(CurrentOrganization::class)->set($organization);

    $ticketType = TicketType::factory()->for($organization)->create(['event_id' => $event->id]);
    $tier = PriceTier::factory()->for($ticketType)->for($organization)->create(['amount' => Money::fromMinorUnits(1000, 'EUR')]);

    foreach ([CarbonImmutable::now()->subDays(2), CarbonImmutable::now()->subDay(), CarbonImmutable::now()] as $paidAt) {
        $order = Order::factory()->for($organization)->create([
            'event_id' => $event->id,
            'status' => OrderStatus::Paid,
            'paid_at' => $paidAt,
        ]);
        $item = OrderItem::factory()->for($organization)->for($order)->create([
            'ticket_type_id' => $ticketType->id,
            'price_tier_id' => $tier->id,
            'quantity' => 1,
        ]);
        Ticket::factory()->for($organization)->create([
            'order_item_id' => $item->id,
            'ticket_type_id' => $ticketType->id,
            'status' => TicketStatus::Valid,
        ]);
    }

    $stats = app(GetEventDashboardStats::class)->handle($event->fresh());
    app(CurrentOrganization::class)->clear();

    $cumulativeValues = array_column($stats->registrationCurve, 'cumulative');
    expect($cumulativeValues)->toBe([1, 2, 3]);
});

it('affiche le tableau de bord d\'un événement pour un membre habilité', function (): void {
    ['event' => $event, 'doorStaff' => $owner] = makeCheckInEvent(MembershipRole::Owner);

    $response = $this->actingAs($owner)->get("/events/{$event->id}/dashboard");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('EventDashboard/Show')
        ->where('stats.confirmed_count', 0));
});

it('refuse le tableau de bord d\'un événement à un membre d\'une autre organisation (404, jamais 403)', function (): void {
    ['event' => $event] = makeCheckInEvent(MembershipRole::Owner);
    // Un membre d'une organisation tierce résout bien un contexte multi-
    // tenant (resolve-organization retombe sur sa propre organisation),
    // mais la RLS ne lui laisse voir aucun événement d'une autre
    // organisation : findOrFail échoue en 404, jamais en 403.
    ['doorStaff' => $memberOfAnotherOrganization] = makeCheckInEvent(MembershipRole::Owner);

    $response = $this->actingAs($memberOfAnotherOrganization)->get("/events/{$event->id}/dashboard");

    $response->assertNotFound();
});
