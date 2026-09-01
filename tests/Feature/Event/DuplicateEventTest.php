<?php

declare(strict_types=1);

use App\Domain\Event\Actions\DuplicateEvent;
use App\Domain\Event\Models\Event;
use App\Domain\Event\Models\EventStatus;
use App\Domain\Event\Models\Venue;
use App\Domain\Organization\Models\MembershipRole;
use App\Domain\Organization\Models\Organization;
use App\Models\User;
use App\Support\MultiTenancy\CurrentOrganization;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;

function organizationWithDuplicatorRole(MembershipRole $role): array
{
    $organization = Organization::factory()->create();
    app(CurrentOrganization::class)->set($organization);

    $user = User::factory()->create();
    $user->memberships()->create(['organization_id' => $organization->id, 'role' => $role]);

    return [$organization, $user];
}

it('duplique un événement avec ses dates décalées, jamais copiées telles quelles', function (): void {
    [$organization, $admin] = organizationWithDuplicatorRole(MembershipRole::Admin);
    $venue = Venue::factory()->for($organization)->create();
    $source = Event::factory()->for($organization)->published()->create([
        'title' => 'Assemblée générale 2025',
        'venue_id' => $venue->id,
        'capacity' => 200,
        'start_at' => CarbonImmutable::parse('2025-09-08T14:30:00Z'),
        'end_at' => CarbonImmutable::parse('2025-09-08T17:30:00Z'),
        'timezone' => 'Africa/Kinshasa',
    ]);

    $newStartAt = CarbonImmutable::parse('2026-09-08T14:30:00Z');
    $duplicate = app(DuplicateEvent::class)->handle($source, $admin, $newStartAt);

    expect($duplicate->id)->not->toBe($source->id);
    expect($duplicate->title)->toBe('Assemblée générale 2025');
    expect($duplicate->venue_id)->toBe($venue->id);
    expect($duplicate->capacity)->toBe(200);
    // Le décalage entre début et fin (3h) doit être préservé, pas seulement le début.
    expect($duplicate->start_at->equalTo($newStartAt))->toBeTrue();
    expect($duplicate->start_at->diffInHours($duplicate->end_at))->toBe(3.0);
});

it('remet le statut à brouillon même si l\'événement source était publié', function (): void {
    [$organization, $admin] = organizationWithDuplicatorRole(MembershipRole::Admin);
    $source = Event::factory()->for($organization)->published()->create();

    $duplicate = app(DuplicateEvent::class)->handle($source, $admin, now()->addYear());

    expect($duplicate->status)->toBe(EventStatus::Draft);
});

it('attribue un slug différent de celui de l\'événement source', function (): void {
    [$organization, $admin] = organizationWithDuplicatorRole(MembershipRole::Admin);
    $source = Event::factory()->for($organization)->create(['slug' => 'assemblee-generale-2025']);

    $duplicate = app(DuplicateEvent::class)->handle($source, $admin, now()->addYear());

    expect($duplicate->slug)->not->toBe($source->slug);
});

it('duplique les sous-événements avec le même décalage de dates, rattachés au nouveau parent', function (): void {
    [$organization, $admin] = organizationWithDuplicatorRole(MembershipRole::Admin);
    $source = Event::factory()->for($organization)->create([
        'start_at' => CarbonImmutable::parse('2025-09-08T09:00:00Z'),
        'end_at' => CarbonImmutable::parse('2025-09-08T18:00:00Z'),
    ]);
    $subEvent = Event::factory()->for($organization)->subEventOf($source)->create([
        'title' => 'Atelier',
        'start_at' => CarbonImmutable::parse('2025-09-08T10:00:00Z'),
        'end_at' => CarbonImmutable::parse('2025-09-08T11:00:00Z'),
    ]);

    $newStartAt = CarbonImmutable::parse('2026-09-08T09:00:00Z');
    $duplicate = app(DuplicateEvent::class)->handle($source->fresh(), $admin, $newStartAt);

    $duplicatedSubEvents = $duplicate->fresh()->subEvents;
    expect($duplicatedSubEvents)->toHaveCount(1);

    $duplicatedSubEvent = $duplicatedSubEvents->first();
    expect($duplicatedSubEvent->title)->toBe('Atelier');
    expect($duplicatedSubEvent->parent_event_id)->toBe($duplicate->id);
    // Le sous-événement dupliqué garde le même écart d'1h avec le début de l'événement.
    expect($duplicate->start_at->diffInHours($duplicatedSubEvent->start_at))->toBe(1.0);
});

it('refuse la duplication à un rôle qui n\'a pas la capacité createEvents', function (): void {
    [$organization, $editor] = organizationWithDuplicatorRole(MembershipRole::Editor);
    $source = Event::factory()->for($organization)->create();

    app(DuplicateEvent::class)->handle($source, $editor, now()->addYear());
})->throws(AuthorizationException::class);
