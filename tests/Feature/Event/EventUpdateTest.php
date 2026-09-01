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

it('refuse la mise à jour à un rôle qui n\'a pas la capacité updateEvents', function (): void {
    [$organization, $doorstaff] = organizationWithEditor(MembershipRole::DoorStaff);
    $event = Event::factory()->for($organization)->create();

    app(UpdateEvent::class)->handle($event, $doorstaff, ['title' => 'Nouveau titre']);
})->throws(AuthorizationException::class);
