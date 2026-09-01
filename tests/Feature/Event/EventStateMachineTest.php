<?php

declare(strict_types=1);

use App\Domain\Event\Actions\ArchiveEvent;
use App\Domain\Event\Actions\PublishEvent;
use App\Domain\Event\InvalidEventTransitionException;
use App\Domain\Event\Models\Event;
use App\Domain\Event\Models\EventLifecycleStatus;
use App\Domain\Event\Models\EventStatus;
use App\Domain\Organization\Models\MembershipRole;
use App\Domain\Organization\Models\Organization;
use App\Models\User;
use App\Support\MultiTenancy\CurrentOrganization;

// Event est protégé par la RLS PostgreSQL (T-002) : toute création via la
// factory exige un contexte d'organisation courant, sinon la base bloque
// l'insertion (comportement voulu, pas un souci de test).
function organizationInContext(): Organization
{
    $organization = Organization::factory()->create();
    app(CurrentOrganization::class)->set($organization);

    return $organization;
}

function eventEditor(Organization $organization): User
{
    $user = User::factory()->create();
    $user->memberships()->create(['organization_id' => $organization->id, 'role' => MembershipRole::Admin]);

    return $user;
}

it('publie un événement brouillon', function (): void {
    $organization = organizationInContext();
    $admin = eventEditor($organization);
    $event = Event::factory()->for($organization)->create(['status' => EventStatus::Draft]);

    $published = app(PublishEvent::class)->handle($event, $admin);

    expect($published->status)->toBe(EventStatus::Published);
});

it('refuse de publier un événement déjà publié', function (): void {
    $organization = organizationInContext();
    $admin = eventEditor($organization);
    $event = Event::factory()->for($organization)->published()->create();

    app(PublishEvent::class)->handle($event, $admin);
})->throws(InvalidEventTransitionException::class);

it('refuse de publier un événement archivé', function (): void {
    $organization = organizationInContext();
    $admin = eventEditor($organization);
    $event = Event::factory()->for($organization)->archived()->create();

    app(PublishEvent::class)->handle($event, $admin);
})->throws(InvalidEventTransitionException::class);

it('archive un événement brouillon ou publié', function (): void {
    $organization = organizationInContext();
    $admin = eventEditor($organization);

    $draft = Event::factory()->for($organization)->create(['status' => EventStatus::Draft]);
    $archived = app(ArchiveEvent::class)->handle($draft, $admin);
    expect($archived->status)->toBe(EventStatus::Archived);

    $published = Event::factory()->for($organization)->published()->create();
    $archived = app(ArchiveEvent::class)->handle($published, $admin);
    expect($archived->status)->toBe(EventStatus::Archived);
});

it('refuse d\'archiver un événement déjà archivé', function (): void {
    $organization = organizationInContext();
    $admin = eventEditor($organization);
    $event = Event::factory()->for($organization)->archived()->create();

    app(ArchiveEvent::class)->handle($event, $admin);
})->throws(InvalidEventTransitionException::class);

it('calcule le statut "live" quand l\'événement publié est en cours', function (): void {
    $organization = organizationInContext();
    $event = Event::factory()->for($organization)->published()->create([
        'start_at' => now()->subHour(),
        'end_at' => now()->addHour(),
        'timezone' => 'UTC',
    ]);

    expect($event->computedStatus())->toBe(EventLifecycleStatus::Live);
});

it('calcule le statut "ended" quand l\'événement publié est terminé', function (): void {
    $organization = organizationInContext();
    $event = Event::factory()->for($organization)->published()->create([
        'start_at' => now()->subDays(2),
        'end_at' => now()->subDay(),
        'timezone' => 'UTC',
    ]);

    expect($event->computedStatus())->toBe(EventLifecycleStatus::Ended);
});

it('calcule le statut "published" quand l\'événement publié n\'a pas encore commencé', function (): void {
    $organization = organizationInContext();
    $event = Event::factory()->for($organization)->published()->create([
        'start_at' => now()->addDay(),
        'end_at' => now()->addDays(2),
        'timezone' => 'UTC',
    ]);

    expect($event->computedStatus())->toBe(EventLifecycleStatus::Published);
});

it('conserve draft et archived tels quels dans le statut calculé', function (): void {
    $organization = organizationInContext();

    $draft = Event::factory()->for($organization)->create(['status' => EventStatus::Draft]);
    expect($draft->computedStatus())->toBe(EventLifecycleStatus::Draft);

    $archived = Event::factory()->for($organization)->archived()->create();
    expect($archived->computedStatus())->toBe(EventLifecycleStatus::Archived);
});
