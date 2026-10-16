<?php

declare(strict_types=1);

use App\Domain\CheckIn\Models\SeatAssignment;
use App\Domain\CheckIn\Models\SeatingAccessibilityNeed;
use App\Domain\CheckIn\Models\SeatingConstraint;
use App\Domain\CheckIn\Models\SeatingTable;
use App\Domain\Organization\Models\MembershipRole;
use App\Support\MultiTenancy\CurrentOrganization;

it('crée une table avec numérotation automatique', function (): void {
    ['event' => $event, 'doorStaff' => $owner] = makeCheckInEvent(MembershipRole::Owner);

    $response = $this->actingAs($owner)->postJson("/events/{$event->id}/seating/tables", [
        'shape' => 'round',
        'capacity' => 8,
    ]);

    $response->assertCreated();
    $response->assertJsonPath('name', 'Table 1');
    $response->assertJsonPath('capacity', 8);
});

it('crée une table marquée accès PMR', function (): void {
    ['event' => $event, 'doorStaff' => $owner] = makeCheckInEvent(MembershipRole::Owner);

    $response = $this->actingAs($owner)->postJson("/events/{$event->id}/seating/tables", [
        'shape' => 'round',
        'capacity' => 8,
        'is_accessible' => true,
    ]);

    $response->assertCreated();
    $response->assertJsonPath('is_accessible', true);
});

it('marque une table comme accessible puis retire ce marquage', function (): void {
    ['organization' => $organization, 'event' => $event, 'doorStaff' => $owner] = makeCheckInEvent(MembershipRole::Owner);
    app(CurrentOrganization::class)->set($organization);
    $table = SeatingTable::factory()->for($organization)->create(['event_id' => $event->id, 'is_accessible' => false]);
    app(CurrentOrganization::class)->clear();

    $marked = $this->actingAs($owner)->patchJson("/events/{$event->id}/seating/tables/{$table->id}", ['is_accessible' => true]);
    $marked->assertOk();
    $marked->assertJsonPath('is_accessible', true);

    $unmarked = $this->actingAs($owner)->patchJson("/events/{$event->id}/seating/tables/{$table->id}", ['is_accessible' => false]);
    $unmarked->assertOk();
    $unmarked->assertJsonPath('is_accessible', false);
});

it('déplace une table (position, taille) dans l\'éditeur visuel', function (): void {
    ['organization' => $organization, 'event' => $event, 'doorStaff' => $owner] = makeCheckInEvent(MembershipRole::Owner);
    app(CurrentOrganization::class)->set($organization);
    $table = SeatingTable::factory()->for($organization)->create(['event_id' => $event->id]);
    app(CurrentOrganization::class)->clear();

    $response = $this->actingAs($owner)->patchJson("/events/{$event->id}/seating/tables/{$table->id}", [
        'position_x' => 250,
        'position_y' => 100,
    ]);

    $response->assertOk();
    $response->assertJsonPath('position_x', 250);
    $response->assertJsonPath('position_y', 100);
});

it('affecte un invité à une table puis le réaffecte à une autre (jamais deux tables à la fois)', function (): void {
    ['organization' => $organization, 'event' => $event, 'doorStaff' => $owner] = makeCheckInEvent(MembershipRole::Owner);
    $attendee = makeCheckedInAttendee($organization, $event);
    app(CurrentOrganization::class)->set($organization);
    $tableA = SeatingTable::factory()->for($organization)->create(['event_id' => $event->id, 'capacity' => 4]);
    $tableB = SeatingTable::factory()->for($organization)->create(['event_id' => $event->id, 'capacity' => 4]);
    app(CurrentOrganization::class)->clear();

    $this->actingAs($owner)->postJson("/events/{$event->id}/seating/tables/{$tableA->id}/assign", [
        'guest_type' => 'attendee',
        'guest_id' => $attendee->id,
    ])->assertNoContent();

    $this->actingAs($owner)->postJson("/events/{$event->id}/seating/tables/{$tableB->id}/assign", [
        'guest_type' => 'attendee',
        'guest_id' => $attendee->id,
    ])->assertNoContent();

    app(CurrentOrganization::class)->set($organization);
    expect(SeatAssignment::query()->where('guest_type', 'attendee')->where('guest_id', $attendee->id)->count())->toBe(1);
    expect(SeatAssignment::query()->where('guest_type', 'attendee')->where('guest_id', $attendee->id)->value('seating_table_id'))->toBe($tableB->id);
    app(CurrentOrganization::class)->clear();
});

it('refuse une affectation qui dépasserait la capacité de la table', function (): void {
    ['organization' => $organization, 'event' => $event, 'doorStaff' => $owner] = makeCheckInEvent(MembershipRole::Owner);
    $ticketA = makePaidTicket($organization, $event);
    $ticketB = makePaidTicket($organization, $event);
    app(CurrentOrganization::class)->set($organization);
    $table = SeatingTable::factory()->for($organization)->create(['event_id' => $event->id, 'capacity' => 1]);
    app(CurrentOrganization::class)->clear();

    $this->actingAs($owner)->postJson("/events/{$event->id}/seating/tables/{$table->id}/assign", [
        'guest_type' => 'ticket',
        'guest_id' => $ticketA->id,
    ])->assertNoContent();

    $response = $this->actingAs($owner)->postJson("/events/{$event->id}/seating/tables/{$table->id}/assign", [
        'guest_type' => 'ticket',
        'guest_id' => $ticketB->id,
    ]);

    $response->assertUnprocessable();
});

it('enregistre une contrainte, normalisée quel que soit l\'ordre des deux invités', function (): void {
    ['organization' => $organization, 'event' => $event, 'doorStaff' => $owner] = makeCheckInEvent(MembershipRole::Owner);

    $this->actingAs($owner)->postJson("/events/{$event->id}/seating/constraints", [
        'guest_a_type' => 'attendee',
        'guest_a_id' => 5,
        'guest_b_type' => 'attendee',
        'guest_b_id' => 2,
        'type' => 'must_not_be_with',
    ])->assertCreated();

    // Même paire, ordre inversé : ne doit pas créer une seconde ligne.
    $this->actingAs($owner)->postJson("/events/{$event->id}/seating/constraints", [
        'guest_a_type' => 'attendee',
        'guest_a_id' => 2,
        'guest_b_type' => 'attendee',
        'guest_b_id' => 5,
        'type' => 'must_not_be_with',
    ])->assertCreated();

    app(CurrentOrganization::class)->set($organization);
    expect(SeatingConstraint::query()->where('event_id', $event->id)->count())->toBe(1);
    app(CurrentOrganization::class)->clear();
});

it('marque un invité comme ayant besoin d\'un accès PMR puis retire ce marquage', function (): void {
    ['organization' => $organization, 'event' => $event, 'doorStaff' => $owner] = makeCheckInEvent(MembershipRole::Owner);

    $this->actingAs($owner)->patchJson("/events/{$event->id}/seating/accessibility", [
        'guest_type' => 'attendee',
        'guest_id' => 7,
        'needs_accessible_seating' => true,
    ])->assertNoContent();

    app(CurrentOrganization::class)->set($organization);
    expect(SeatingAccessibilityNeed::query()->where('event_id', $event->id)->where('guest_type', 'attendee')->where('guest_id', 7)->exists())->toBeTrue();
    app(CurrentOrganization::class)->clear();

    $this->actingAs($owner)->patchJson("/events/{$event->id}/seating/accessibility", [
        'guest_type' => 'attendee',
        'guest_id' => 7,
        'needs_accessible_seating' => false,
    ])->assertNoContent();

    app(CurrentOrganization::class)->set($organization);
    expect(SeatingAccessibilityNeed::query()->where('event_id', $event->id)->where('guest_type', 'attendee')->where('guest_id', 7)->exists())->toBeFalse();
    app(CurrentOrganization::class)->clear();
});

it('lance le placement automatique et affecte les invités non placés', function (): void {
    ['organization' => $organization, 'event' => $event, 'doorStaff' => $owner] = makeCheckInEvent(MembershipRole::Owner);
    makeCheckedInAttendee($organization, $event);
    makePaidTicket($organization, $event);
    app(CurrentOrganization::class)->set($organization);
    SeatingTable::factory()->for($organization)->create(['event_id' => $event->id, 'capacity' => 10]);
    app(CurrentOrganization::class)->clear();

    $response = $this->actingAs($owner)->postJson("/events/{$event->id}/seating/auto-place");

    $response->assertOk();
    $response->assertJsonPath('placed_count', 2);
    $response->assertJsonPath('unplaced_count', 0);
});

it('place automatiquement un invité à mobilité réduite sur la table accessible, pas sur celle qui tasse le mieux', function (): void {
    ['organization' => $organization, 'event' => $event, 'doorStaff' => $owner] = makeCheckInEvent(MembershipRole::Owner);
    $attendee = makeCheckedInAttendee($organization, $event);
    app(CurrentOrganization::class)->set($organization);
    // La table non accessible laisserait moins de place vide (meilleur
    // tassement) : sans la règle d'accessibilité, l'heuristique l'aurait choisie.
    SeatingTable::factory()->for($organization)->create(['event_id' => $event->id, 'capacity' => 2, 'is_accessible' => false]);
    SeatingTable::factory()->for($organization)->accessible()->create(['event_id' => $event->id, 'capacity' => 8]);
    app(CurrentOrganization::class)->clear();

    $this->actingAs($owner)->patchJson("/events/{$event->id}/seating/accessibility", [
        'guest_type' => 'attendee',
        'guest_id' => $attendee->id,
        'needs_accessible_seating' => true,
    ])->assertNoContent();

    $this->actingAs($owner)->postJson("/events/{$event->id}/seating/auto-place")
        ->assertOk()
        ->assertJsonPath('placed_count', 1);

    app(CurrentOrganization::class)->set($organization);
    $assignment = SeatAssignment::query()->where('event_id', $event->id)->where('guest_id', $attendee->id)->sole();
    expect(SeatingTable::query()->find($assignment->seating_table_id)->is_accessible)->toBeTrue();
    app(CurrentOrganization::class)->clear();
});

it('affiche le plan de table pour un membre habilité', function (): void {
    ['event' => $event, 'doorStaff' => $owner] = makeCheckInEvent(MembershipRole::Owner);

    $response = $this->actingAs($owner)->get("/events/{$event->id}/seating");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->component('Seating/Show'));
});

it('refuse le plan de table à un membre d\'une autre organisation (404)', function (): void {
    ['event' => $event] = makeCheckInEvent(MembershipRole::Owner);
    ['doorStaff' => $memberOfAnotherOrganization] = makeCheckInEvent(MembershipRole::Owner);

    $response = $this->actingAs($memberOfAnotherOrganization)->get("/events/{$event->id}/seating");

    $response->assertNotFound();
});

it('affiche la vue d\'ensemble en lecture seule de la salle pour un membre habilité', function (): void {
    ['organization' => $organization, 'event' => $event, 'doorStaff' => $owner] = makeCheckInEvent(MembershipRole::Owner);
    app(CurrentOrganization::class)->set($organization);
    SeatingTable::factory()->for($organization)->create(['event_id' => $event->id]);
    app(CurrentOrganization::class)->clear();

    $response = $this->actingAs($owner)->get("/events/{$event->id}/seating/overview");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->component('Seating/Overview'));
});

it('refuse la vue d\'ensemble de la salle à un membre d\'une autre organisation (404)', function (): void {
    ['event' => $event] = makeCheckInEvent(MembershipRole::Owner);
    ['doorStaff' => $memberOfAnotherOrganization] = makeCheckInEvent(MembershipRole::Owner);

    $response = $this->actingAs($memberOfAnotherOrganization)->get("/events/{$event->id}/seating/overview");

    $response->assertNotFound();
});

it('exporte le plan de salle et les listes par table en PDF', function (): void {
    ['organization' => $organization, 'event' => $event, 'doorStaff' => $owner] = makeCheckInEvent(MembershipRole::Owner);
    app(CurrentOrganization::class)->set($organization);
    SeatingTable::factory()->for($organization)->create(['event_id' => $event->id]);
    app(CurrentOrganization::class)->clear();

    $plan = $this->actingAs($owner)->get("/events/{$event->id}/seating/export/plan");
    $plan->assertOk();
    $plan->assertHeader('content-type', 'application/pdf');

    $lists = $this->actingAs($owner)->get("/events/{$event->id}/seating/export/lists");
    $lists->assertOk();
    $lists->assertHeader('content-type', 'application/pdf');
});
