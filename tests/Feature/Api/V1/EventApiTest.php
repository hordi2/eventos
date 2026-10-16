<?php

declare(strict_types=1);

use App\Domain\Event\Models\Event;
use App\Domain\Event\Models\EventStatus;
use App\Domain\Organization\Models\MembershipRole;
use App\Domain\Organization\Models\Organization;
use App\Models\User;
use App\Support\MultiTenancy\CurrentOrganization;

it('liste les événements de la seule organisation de la clé API, paginés par curseur', function (): void {
    ['organization' => $organization, 'doorStaff' => $owner] = makeCheckInEvent(MembershipRole::Owner);
    app(CurrentOrganization::class)->set($organization);
    Event::factory()->for($organization)->count(2)->create();
    app(CurrentOrganization::class)->clear();

    $otherOrganization = Organization::factory()->create();
    app(CurrentOrganization::class)->set($otherOrganization);
    Event::factory()->for($otherOrganization)->create();
    app(CurrentOrganization::class)->clear();

    $response = $this->actingAs($owner, 'sanctum')->getJson('/api/v1/events?per_page=10');

    $response->assertOk();
    // makeCheckInEvent crée déjà un événement : 1 + 2 = 3, jamais celui de l'autre organisation.
    expect($response->json('data'))->toHaveCount(3);
});

it('filtre les événements par statut', function (): void {
    ['organization' => $organization, 'event' => $event, 'doorStaff' => $owner] = makeCheckInEvent(MembershipRole::Owner);
    app(CurrentOrganization::class)->set($organization);
    $event->update(['status' => EventStatus::Draft]);
    app(CurrentOrganization::class)->clear();

    $response = $this->actingAs($owner, 'sanctum')->getJson('/api/v1/events?status=published');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(0);
});

it('affiche le détail d\'un événement avec son statut de cycle de vie', function (): void {
    ['organization' => $organization, 'event' => $event, 'doorStaff' => $owner] = makeCheckInEvent(MembershipRole::Owner);
    app(CurrentOrganization::class)->set($organization);
    $event->update(['start_at' => now()->subHour(), 'end_at' => now()->addHour(), 'timezone' => 'UTC']);
    app(CurrentOrganization::class)->clear();

    $response = $this->actingAs($owner, 'sanctum')->getJson("/api/v1/events/{$event->id}");

    $response->assertOk();
    $response->assertJsonPath('data.id', $event->id);
    $response->assertJsonPath('data.lifecycle_status', 'live');
});

it('renvoie 404 pour l\'événement d\'une autre organisation', function (): void {
    ['doorStaff' => $owner] = makeCheckInEvent(MembershipRole::Owner);

    $otherOrganization = Organization::factory()->create();
    app(CurrentOrganization::class)->set($otherOrganization);
    $otherEvent = Event::factory()->for($otherOrganization)->create();
    app(CurrentOrganization::class)->clear();

    $response = $this->actingAs($owner, 'sanctum')->getJson("/api/v1/events/{$otherEvent->id}");

    $response->assertNotFound();
});

it('refuse l\'accès à un utilisateur qui n\'appartient à aucune organisation', function (): void {
    $user = User::factory()->create();

    $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/events');

    $response->assertForbidden();
});
