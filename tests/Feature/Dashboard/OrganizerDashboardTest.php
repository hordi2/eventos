<?php

declare(strict_types=1);

use App\Domain\Contact\Models\Contact;
use App\Domain\Event\Models\Event;
use App\Domain\Event\Models\EventStatus;
use App\Domain\Form\Models\RegistrationStatus;
use App\Domain\Organization\Models\MembershipRole;
use App\Support\MultiTenancy\CurrentOrganization;
use Carbon\CarbonImmutable;

it('liste les événements de l\'organisation avec leur répartition d\'inscriptions', function (): void {
    ['organization' => $organization, 'event' => $event, 'doorStaff' => $owner] = makeCheckInEvent(MembershipRole::Owner);

    app(CurrentOrganization::class)->set($organization);
    $contacts = Contact::factory()->for($organization)->count(4)->create();
    registerContactForEvent($organization, $event, $contacts[0], RegistrationStatus::Confirmed);
    registerContactForEvent($organization, $event, $contacts[1], RegistrationStatus::Confirmed);
    registerContactForEvent($organization, $event, $contacts[2], RegistrationStatus::Waitlisted);
    registerContactForEvent($organization, $event, $contacts[3], RegistrationStatus::Cancelled);
    app(CurrentOrganization::class)->clear();

    $response = $this->actingAs($owner)->get('/dashboard');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->component('Dashboard')
        ->where('events.0.id', $event->id)
        ->where('events.0.stats.confirmed', 2)
        ->where('events.0.stats.waitlisted', 1)
        ->where('events.0.stats.cancelled', 1));
});

it('distingue les événements en cours des événements passés', function (): void {
    [$organization, $owner] = organizationWithContactRole(MembershipRole::Owner);
    $upcoming = Event::factory()->for($organization)->create([
        'status' => EventStatus::Published,
        'start_at' => CarbonImmutable::now()->addWeek(),
        'end_at' => CarbonImmutable::now()->addWeek()->addHours(3),
    ]);
    $past = Event::factory()->for($organization)->create([
        'status' => EventStatus::Published,
        'start_at' => CarbonImmutable::now()->subWeeks(2),
        'end_at' => CarbonImmutable::now()->subWeeks(2)->addHours(3),
    ]);
    app(CurrentOrganization::class)->clear();

    $response = $this->actingAs($owner)->get('/dashboard');

    // Triées par start_at décroissant (GetOrganizationEventSummaries) :
    // l'événement à venir (dans une semaine) précède celui du passé.
    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('events.0.id', $upcoming->id)
        ->where('events.0.is_past', false)
        ->where('events.1.id', $past->id)
        ->where('events.1.is_past', true));
});

it('ne montre aucun événement à un rôle sans capacité updateEvents', function (): void {
    ['doorStaff' => $viewer] = makeCheckInEvent(MembershipRole::Viewer);

    $response = $this->actingAs($viewer)->get('/dashboard');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->where('events', [])->where('canCreateEvents', false));
});
