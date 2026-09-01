<?php

declare(strict_types=1);

use App\Domain\Event\Actions\CreateEvent;
use App\Domain\Event\Actions\DeleteEvent;
use App\Domain\Event\CannotDeleteEventException;
use App\Domain\Event\InvalidSubEventException;
use App\Domain\Event\Models\Event;
use App\Domain\Event\Models\EventType;
use App\Domain\Organization\Models\MembershipRole;
use App\Domain\Organization\Models\Organization;
use App\Models\User;
use App\Support\MultiTenancy\CurrentOrganization;

function organizationWithSubEventRole(MembershipRole $role): array
{
    $organization = Organization::factory()->create();
    app(CurrentOrganization::class)->set($organization);

    $user = User::factory()->create();
    $user->memberships()->create(['organization_id' => $organization->id, 'role' => $role]);

    return [$organization, $user];
}

it('crée un sous-événement rattaché à un événement parent', function (): void {
    [$organization, $admin] = organizationWithSubEventRole(MembershipRole::Admin);
    $parent = Event::factory()->for($organization)->create();

    $subEvent = app(CreateEvent::class)->handle($organization, $admin, [
        'title' => 'Dîner de gala',
        'type' => EventType::Gala,
        'start_at' => now()->addWeek(),
        'end_at' => now()->addWeek()->addHours(2),
        'timezone' => 'UTC',
        'parent_event_id' => $parent->id,
    ]);

    expect($subEvent->parent_event_id)->toBe($parent->id);
    expect($subEvent->isSubEvent())->toBeTrue();
    expect($parent->fresh()->subEvents)->toHaveCount(1);
});

it('gère la capacité d\'un sous-événement indépendamment de celle du parent', function (): void {
    [$organization, $admin] = organizationWithSubEventRole(MembershipRole::Admin);
    $parent = Event::factory()->for($organization)->create(['capacity' => 500]);

    $subEvent = app(CreateEvent::class)->handle($organization, $admin, [
        'title' => 'Atelier limité',
        'start_at' => now()->addWeek(),
        'timezone' => 'UTC',
        'parent_event_id' => $parent->id,
        'capacity' => 30,
    ]);

    expect($parent->fresh()->capacity)->toBe(500);
    expect($subEvent->capacity)->toBe(30);
});

it('refuse qu\'un sous-événement ait lui-même un sous-événement', function (): void {
    [$organization, $admin] = organizationWithSubEventRole(MembershipRole::Admin);
    $parent = Event::factory()->for($organization)->create();
    $subEvent = Event::factory()->for($organization)->subEventOf($parent)->create();

    app(CreateEvent::class)->handle($organization, $admin, [
        'title' => 'Sous-sous-événement',
        'start_at' => now()->addWeek(),
        'timezone' => 'UTC',
        'parent_event_id' => $subEvent->id,
    ]);
})->throws(InvalidSubEventException::class);

it('détecte un conflit d\'horaires entre deux sous-événements qui se chevauchent', function (): void {
    [$organization] = organizationWithSubEventRole(MembershipRole::Admin);
    $parent = Event::factory()->for($organization)->create();

    $sessionA = Event::factory()->for($organization)->subEventOf($parent)->create([
        'start_at' => now()->addWeek(),
        'end_at' => now()->addWeek()->addHours(2),
        'timezone' => 'UTC',
    ]);
    $sessionB = Event::factory()->for($organization)->subEventOf($parent)->create([
        'start_at' => now()->addWeek()->addHour(),
        'end_at' => now()->addWeek()->addHours(3),
        'timezone' => 'UTC',
    ]);

    $conflicts = $parent->fresh()->detectSubEventScheduleConflicts();
    $conflictIds = array_map(fn (array $pair): array => [$pair[0]->id, $pair[1]->id], $conflicts);

    expect($conflicts)->toHaveCount(1);
    expect($conflictIds[0])->toEqualCanonicalizing([$sessionA->id, $sessionB->id]);
});

it('ne signale aucun conflit entre des sous-événements aux horaires disjoints', function (): void {
    [$organization] = organizationWithSubEventRole(MembershipRole::Admin);
    $parent = Event::factory()->for($organization)->create();

    Event::factory()->for($organization)->subEventOf($parent)->create([
        'start_at' => now()->addWeek(),
        'end_at' => now()->addWeek()->addHours(2),
        'timezone' => 'UTC',
    ]);
    Event::factory()->for($organization)->subEventOf($parent)->create([
        'start_at' => now()->addWeek()->addHours(2),
        'end_at' => now()->addWeek()->addHours(4),
        'timezone' => 'UTC',
    ]);

    expect($parent->fresh()->detectSubEventScheduleConflicts())->toBeEmpty();
});

it('refuse de supprimer un événement qui a encore des sous-événements', function (): void {
    [$organization, $admin] = organizationWithSubEventRole(MembershipRole::Admin);
    $parent = Event::factory()->for($organization)->create();
    Event::factory()->for($organization)->subEventOf($parent)->create();

    app(DeleteEvent::class)->handle($parent, $admin);
})->throws(CannotDeleteEventException::class);

it('supprime un sous-événement sans sous-événements propres', function (): void {
    [$organization, $admin] = organizationWithSubEventRole(MembershipRole::Admin);
    $parent = Event::factory()->for($organization)->create();
    $subEvent = Event::factory()->for($organization)->subEventOf($parent)->create();

    app(DeleteEvent::class)->handle($subEvent, $admin);

    expect(Event::query()->find($subEvent->id))->toBeNull();
    expect(Event::withTrashed()->find($subEvent->id))->not->toBeNull();
});

it('supprime un événement sans sous-événements normalement', function (): void {
    [$organization, $admin] = organizationWithSubEventRole(MembershipRole::Admin);
    $event = Event::factory()->for($organization)->create();

    app(DeleteEvent::class)->handle($event, $admin);

    expect(Event::query()->find($event->id))->toBeNull();
});
