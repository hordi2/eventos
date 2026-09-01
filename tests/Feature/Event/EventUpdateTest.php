<?php

declare(strict_types=1);

use App\Domain\Event\Actions\UpdateEvent;
use App\Domain\Event\Models\Event;
use App\Domain\Organization\Models\MembershipRole;
use App\Domain\Organization\Models\Organization;
use App\Models\User;
use App\Support\MultiTenancy\CurrentOrganization;
use Illuminate\Auth\Access\AuthorizationException;

function organizationWithEditor(MembershipRole $role): array
{
    $organization = Organization::factory()->create();
    app(CurrentOrganization::class)->set($organization);

    $user = User::factory()->create();
    $user->memberships()->create(['organization_id' => $organization->id, 'role' => $role]);

    return [$organization, $user];
}

it('met à jour les champs d\'un événement', function (): void {
    [$organization, $admin] = organizationWithEditor(MembershipRole::Admin);
    $event = Event::factory()->for($organization)->create(['title' => 'Ancien titre']);

    $updated = app(UpdateEvent::class)->handle($event, $admin, ['title' => 'Nouveau titre']);

    expect($updated->title)->toBe('Nouveau titre');
});

it('revalide le fuseau horaire lors d\'un changement', function (): void {
    [$organization, $admin] = organizationWithEditor(MembershipRole::Admin);
    $event = Event::factory()->for($organization)->create();

    app(UpdateEvent::class)->handle($event, $admin, ['timezone' => 'Pas/UnFuseau']);
})->throws(InvalidArgumentException::class);

it('revalide l\'unicité du slug lors d\'un changement', function (): void {
    [$organization, $admin] = organizationWithEditor(MembershipRole::Admin);
    Event::factory()->for($organization)->create(['slug' => 'evenement-existant']);
    $event = Event::factory()->for($organization)->create(['slug' => 'autre-evenement']);

    $updated = app(UpdateEvent::class)->handle($event, $admin, ['slug' => 'evenement-existant']);

    expect($updated->slug)->toBe('evenement-existant-1');
});

it('interprète une date de début envoyée sans fuseau dans le fuseau de l\'événement', function (): void {
    [$organization, $admin] = organizationWithEditor(MembershipRole::Admin);
    $event = Event::factory()->for($organization)->create(['timezone' => 'Africa/Kinshasa']);

    $updated = app(UpdateEvent::class)->handle($event, $admin, ['start_at' => '2026-09-08T14:30']);

    // 14h30 à Kinshasa (UTC+1, sans heure d'été) doit être stocké comme 13h30 UTC.
    expect($updated->start_at->utc()->format('H:i'))->toBe('13:30');
});

it('recalcule une fin par défaut quand end_at est envoyé vide', function (): void {
    [$organization, $admin] = organizationWithEditor(MembershipRole::Admin);
    $event = Event::factory()->for($organization)->create(['timezone' => 'UTC']);

    $updated = app(UpdateEvent::class)->handle($event, $admin, [
        'start_at' => '2026-09-08T14:30',
        'end_at' => null,
    ]);

    expect($updated->start_at->diffInHours($updated->end_at))->toBe(3.0);
});

it('refuse la mise à jour à un rôle qui n\'a pas la capacité updateEvents', function (): void {
    [$organization, $doorstaff] = organizationWithEditor(MembershipRole::DoorStaff);
    $event = Event::factory()->for($organization)->create();

    app(UpdateEvent::class)->handle($event, $doorstaff, ['title' => 'Nouveau titre']);
})->throws(AuthorizationException::class);
