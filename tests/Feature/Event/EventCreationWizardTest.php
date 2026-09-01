<?php

declare(strict_types=1);

use App\Domain\Event\Models\Event;
use App\Domain\Event\Models\EventStatus;
use App\Domain\Event\Models\EventType;
use App\Domain\Event\Models\Venue;
use App\Domain\Organization\Models\MembershipRole;
use App\Domain\Organization\Models\Organization;
use App\Models\User;
use App\Support\MultiTenancy\CurrentOrganization;

function organizationWithRole(MembershipRole $role): array
{
    $organization = Organization::factory()->create();
    app(CurrentOrganization::class)->set($organization);

    $user = User::factory()->create();
    $user->memberships()->create(['organization_id' => $organization->id, 'role' => $role]);

    return [$organization, $user];
}

it('affiche l\'assistant de création vide pour un rôle autorisé', function (): void {
    [, $admin] = organizationWithRole(MembershipRole::Admin);

    $response = $this->actingAs($admin)->get('/events/create');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->where('event', null));
});

it('refuse l\'accès à l\'assistant de création pour un rôle sans createEvents', function (): void {
    [, $editor] = organizationWithRole(MembershipRole::Editor);

    $response = $this->actingAs($editor)->get('/events/create');

    $response->assertForbidden();
});

it('crée un événement brouillon avec seulement le titre, la date et le fuseau', function (): void {
    [$organization, $admin] = organizationWithRole(MembershipRole::Admin);

    $response = $this->actingAs($admin)->post('/events', [
        'title' => 'Assemblée générale 2026',
        'start_at' => '2026-09-08T14:30',
        'timezone' => 'Africa/Kinshasa',
    ]);

    $event = Event::query()->where('organization_id', $organization->id)->firstOrFail();

    $response->assertRedirect(route('events.edit', $event));
    expect($event->title)->toBe('Assemblée générale 2026');
    expect($event->type)->toBe(EventType::Other);
    expect($event->status)->toBe(EventStatus::Draft);
    // 14h30 à Kinshasa (UTC+1, sans heure d'été) doit être stocké comme 13h30 UTC.
    expect($event->start_at->utc()->format('H:i'))->toBe('13:30');
    expect($event->start_at->diffInHours($event->end_at))->toBe(3.0);
});

it('refuse la création sans titre', function (): void {
    [, $admin] = organizationWithRole(MembershipRole::Admin);

    $response = $this->actingAs($admin)->post('/events', [
        'start_at' => '2026-09-08T14:30',
        'timezone' => 'Africa/Kinshasa',
    ]);

    $response->assertSessionHasErrors('title');
});

it('affiche le brouillon existant sur la page d\'édition', function (): void {
    [$organization, $admin] = organizationWithRole(MembershipRole::Admin);
    $event = Event::factory()->for($organization)->create(['title' => 'Mon événement']);

    $response = $this->actingAs($admin)->get("/events/{$event->id}/edit");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('event.id', $event->id)
        ->where('event.title', 'Mon événement'));
});

it('bloque l\'accès à un brouillon d\'une autre organisation', function (): void {
    [, $admin] = organizationWithRole(MembershipRole::Admin);

    $otherOrganization = Organization::factory()->create();
    app(CurrentOrganization::class)->set($otherOrganization);
    $otherEvent = Event::factory()->for($otherOrganization)->create();

    $response = $this->actingAs($admin)->get("/events/{$otherEvent->id}/edit");

    $response->assertNotFound();
});

it('met à jour un brouillon existant', function (): void {
    [$organization, $editor] = organizationWithRole(MembershipRole::Editor);
    $event = Event::factory()->for($organization)->create(['title' => 'Ancien titre']);

    $response = $this->actingAs($editor)->patch("/events/{$event->id}", [
        'title' => 'Nouveau titre',
        'start_at' => '2026-09-08T14:30',
        'timezone' => $event->timezone,
    ]);

    $response->assertRedirect(route('events.edit', $event));
    expect($event->fresh()->title)->toBe('Nouveau titre');
});

it('refuse la mise à jour à un rôle sans updateEvents', function (): void {
    [$organization, $viewer] = organizationWithRole(MembershipRole::Viewer);
    $event = Event::factory()->for($organization)->create();

    $response = $this->actingAs($viewer)->patch("/events/{$event->id}", [
        'title' => 'Nouveau titre',
        'start_at' => '2026-09-08T14:30',
        'timezone' => $event->timezone,
    ]);

    $response->assertForbidden();
});

it('attache un lieu déjà saisi à un nouvel événement', function (): void {
    [$organization, $admin] = organizationWithRole(MembershipRole::Admin);
    $venue = Venue::factory()->for($organization)->create();

    $response = $this->actingAs($admin)->post('/events', [
        'title' => 'Gala annuel',
        'start_at' => '2026-09-08T14:30',
        'timezone' => 'Africa/Kinshasa',
        'venue_id' => $venue->id,
    ]);

    $event = Event::query()->where('organization_id', $organization->id)->firstOrFail();
    $response->assertRedirect(route('events.edit', $event));
    expect($event->venue_id)->toBe($venue->id);
});

it('crée un nouveau lieu à la volée et l\'attache à l\'événement', function (): void {
    [$organization, $admin] = organizationWithRole(MembershipRole::Admin);

    $response = $this->actingAs($admin)->post('/events', [
        'title' => 'Gala annuel',
        'start_at' => '2026-09-08T14:30',
        'timezone' => 'Africa/Kinshasa',
        'venue_name' => 'Salle des fêtes de la Gombe',
        'venue_address' => '12 avenue de la Paix, Kinshasa',
    ]);

    $event = Event::query()->where('organization_id', $organization->id)->firstOrFail();
    $response->assertRedirect(route('events.edit', $event));
    expect($event->venue)->not->toBeNull();
    expect($event->venue->name)->toBe('Salle des fêtes de la Gombe');
    expect($event->venue->organization_id)->toBe($organization->id);
});

it('refuse un venue_id appartenant à une autre organisation', function (): void {
    [, $admin] = organizationWithRole(MembershipRole::Admin);

    $otherOrganization = Organization::factory()->create();
    app(CurrentOrganization::class)->set($otherOrganization);
    $otherVenue = Venue::factory()->for($otherOrganization)->create();

    $response = $this->actingAs($admin)->post('/events', [
        'title' => 'Gala annuel',
        'start_at' => '2026-09-08T14:30',
        'timezone' => 'Africa/Kinshasa',
        'venue_id' => $otherVenue->id,
    ]);

    $response->assertSessionHasErrors('venue_id');
});
