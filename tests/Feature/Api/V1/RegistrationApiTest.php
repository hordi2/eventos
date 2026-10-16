<?php

declare(strict_types=1);

use App\Domain\Event\Models\Event;
use App\Domain\Organization\Models\MembershipRole;
use App\Domain\Organization\Models\Organization;
use App\Support\MultiTenancy\CurrentOrganization;

it('liste les inscriptions d\'un événement, sans téléphone ni réponses', function (): void {
    ['organization' => $organization, 'event' => $event, 'doorStaff' => $owner] = makeCheckInEvent(MembershipRole::Owner);
    makeCheckedInAttendee($organization, $event);

    $response = $this->actingAs($owner, 'sanctum')->getJson("/api/v1/events/{$event->id}/registrations");

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1);
    $response->assertJsonMissingPath('data.0.phone_e164');
    $response->assertJsonMissingPath('data.0.answers');
    $response->assertJsonStructure(['data' => [['id', 'event_id', 'status', 'email', 'first_name', 'last_name', 'submitted_at', 'cancelled_at']]]);
});

it('renvoie 404 pour les inscriptions d\'un événement d\'une autre organisation', function (): void {
    ['doorStaff' => $owner] = makeCheckInEvent(MembershipRole::Owner);

    $otherOrganization = Organization::factory()->create();
    app(CurrentOrganization::class)->set($otherOrganization);
    $otherEvent = Event::factory()->for($otherOrganization)->create();
    app(CurrentOrganization::class)->clear();

    $response = $this->actingAs($owner, 'sanctum')->getJson("/api/v1/events/{$otherEvent->id}/registrations");

    $response->assertNotFound();
});

it('affiche le détail d\'une inscription', function (): void {
    ['organization' => $organization, 'event' => $event, 'doorStaff' => $owner] = makeCheckInEvent(MembershipRole::Owner);
    $attendee = makeCheckedInAttendee($organization, $event);

    app(CurrentOrganization::class)->set($organization);
    $registrationId = $attendee->registration_id;
    app(CurrentOrganization::class)->clear();

    $response = $this->actingAs($owner, 'sanctum')->getJson("/api/v1/events/{$event->id}/registrations/{$registrationId}");

    $response->assertOk();
    $response->assertJsonPath('data.id', $registrationId);
});
