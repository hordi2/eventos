<?php

declare(strict_types=1);

use App\Domain\Event\Actions\CreateEvent;
use App\Domain\Event\Models\Event;
use App\Domain\Event\Models\EventType;
use App\Domain\Organization\Models\MembershipRole;
use App\Domain\Organization\Models\Organization;
use App\Models\User;
use App\Support\MultiTenancy\CurrentOrganization;
use Illuminate\Auth\Access\AuthorizationException;

function organizationWithMember(MembershipRole $role): array
{
    $organization = Organization::factory()->create();
    app(CurrentOrganization::class)->set($organization);

    $user = User::factory()->create();
    $user->memberships()->create(['organization_id' => $organization->id, 'role' => $role]);

    return [$organization, $user];
}

it('crée un événement en brouillon avec un slug généré depuis le titre', function (): void {
    [$organization, $admin] = organizationWithMember(MembershipRole::Admin);

    $event = app(CreateEvent::class)->handle($organization, $admin, [
        'title' => 'Conférence annuelle des partenaires',
        'type' => EventType::Conference,
        'start_at' => now()->addWeek(),
        'end_at' => now()->addWeek()->addHours(3),
        'timezone' => 'Africa/Kinshasa',
    ]);

    expect($event->slug)->toBe('conference-annuelle-des-partenaires');
    expect($event->status->value)->toBe('draft');
    expect($event->created_by)->toBe($admin->id);
});

it('respecte un slug fourni explicitement', function (): void {
    [$organization, $admin] = organizationWithMember(MembershipRole::Owner);

    $event = app(CreateEvent::class)->handle($organization, $admin, [
        'title' => 'Titre quelconque',
        'slug' => 'mon-slug-personnalise',
        'type' => EventType::Wedding,
        'start_at' => now()->addWeek(),
        'end_at' => now()->addWeek()->addHours(3),
        'timezone' => 'UTC',
    ]);

    expect($event->slug)->toBe('mon-slug-personnalise');
});

it('ajoute un suffixe si le slug existe déjà dans la même organisation', function (): void {
    [$organization, $admin] = organizationWithMember(MembershipRole::Owner);
    Event::factory()->for($organization)->create(['slug' => 'gala-annuel']);

    $event = app(CreateEvent::class)->handle($organization, $admin, [
        'title' => 'Gala annuel',
        'type' => EventType::Gala,
        'start_at' => now()->addWeek(),
        'end_at' => now()->addWeek()->addHours(3),
        'timezone' => 'UTC',
    ]);

    expect($event->slug)->toBe('gala-annuel-1');
});

it('autorise le même slug dans deux organisations différentes', function (): void {
    [$organizationA, $adminA] = organizationWithMember(MembershipRole::Owner);
    Event::factory()->for($organizationA)->create(['slug' => 'gala-annuel']);

    [$organizationB, $adminB] = organizationWithMember(MembershipRole::Owner);

    $event = app(CreateEvent::class)->handle($organizationB, $adminB, [
        'title' => 'Gala annuel',
        'type' => EventType::Gala,
        'start_at' => now()->addWeek(),
        'end_at' => now()->addWeek()->addHours(3),
        'timezone' => 'UTC',
    ]);

    expect($event->slug)->toBe('gala-annuel');
});

it('utilise le type "other" et une fin à +3h par défaut quand ils sont omis', function (): void {
    [$organization, $admin] = organizationWithMember(MembershipRole::Owner);

    $event = app(CreateEvent::class)->handle($organization, $admin, [
        'title' => 'Événement sans détails',
        'start_at' => now()->addWeek(),
        'timezone' => 'UTC',
    ]);

    expect($event->type)->toBe(EventType::Other);
    expect($event->start_at->diffInHours($event->end_at))->toBe(3.0);
});

it('interprète l\'échéance de modification dans le fuseau de l\'événement, pas celui du serveur', function (): void {
    [$organization, $admin] = organizationWithMember(MembershipRole::Owner);

    $event = app(CreateEvent::class)->handle($organization, $admin, [
        'title' => 'Conférence',
        'type' => EventType::Conference,
        'start_at' => now()->addWeek(),
        'end_at' => now()->addWeek()->addHours(3),
        'timezone' => 'Africa/Kinshasa',
        'edit_deadline' => '2026-09-08T14:30',
    ]);

    // 14h30 à Kinshasa (UTC+1, sans heure d'été) doit être stockée comme 13h30 UTC.
    expect($event->edit_deadline->utc()->format('H:i'))->toBe('13:30');
});

it('rejette un fuseau horaire qui n\'est pas dans la liste IANA', function (): void {
    [$organization, $admin] = organizationWithMember(MembershipRole::Owner);

    app(CreateEvent::class)->handle($organization, $admin, [
        'title' => 'Test',
        'type' => EventType::Other,
        'start_at' => now()->addWeek(),
        'end_at' => now()->addWeek()->addHours(3),
        'timezone' => 'Pas/UnFuseau',
    ]);
})->throws(InvalidArgumentException::class);

it('refuse la création à un rôle qui n\'a pas la capacité createEvents', function (): void {
    [$organization, $editor] = organizationWithMember(MembershipRole::Editor);

    app(CreateEvent::class)->handle($organization, $editor, [
        'title' => 'Test',
        'type' => EventType::Other,
        'start_at' => now()->addWeek(),
        'end_at' => now()->addWeek()->addHours(3),
        'timezone' => 'UTC',
    ]);
})->throws(AuthorizationException::class);
