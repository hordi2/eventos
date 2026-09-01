<?php

declare(strict_types=1);

use App\Domain\Event\Actions\CreateVenue;
use App\Domain\Event\Actions\UpdateVenue;
use App\Domain\Event\Models\Venue;
use App\Domain\Organization\Models\MembershipRole;
use App\Domain\Organization\Models\Organization;
use App\Models\User;
use App\Support\MultiTenancy\CurrentOrganization;
use Illuminate\Auth\Access\AuthorizationException;

function organizationWithVenueRole(MembershipRole $role): array
{
    $organization = Organization::factory()->create();
    app(CurrentOrganization::class)->set($organization);

    $user = User::factory()->create();
    $user->memberships()->create(['organization_id' => $organization->id, 'role' => $role]);

    return [$organization, $user];
}

it('crée un lieu', function (): void {
    [$organization, $admin] = organizationWithVenueRole(MembershipRole::Admin);

    $venue = app(CreateVenue::class)->handle($organization, $admin, [
        'name' => 'Salle des fêtes de la Gombe',
        'address' => '12 avenue de la Paix, Kinshasa',
        'access_instructions' => "Entrée par l'arrière du bâtiment",
        'parking_info' => 'Parking gratuit sur place',
    ]);

    expect($venue->name)->toBe('Salle des fêtes de la Gombe');
    expect($venue->address)->toBe('12 avenue de la Paix, Kinshasa');
    expect($venue->access_instructions)->toBe("Entrée par l'arrière du bâtiment");
    expect($venue->organization_id)->toBe($organization->id);
});

it('refuse la création d\'un lieu à un rôle qui n\'a pas la capacité createEvents', function (): void {
    [$organization, $editor] = organizationWithVenueRole(MembershipRole::Editor);

    app(CreateVenue::class)->handle($organization, $editor, [
        'name' => 'Test',
        'address' => 'Test',
    ]);
})->throws(AuthorizationException::class);

it('met à jour un lieu existant', function (): void {
    [$organization, $admin] = organizationWithVenueRole(MembershipRole::Admin);
    $venue = Venue::factory()->for($organization)->create(['name' => 'Ancien nom']);

    $updated = app(UpdateVenue::class)->handle($venue, $admin, ['name' => 'Nouveau nom']);

    expect($updated->name)->toBe('Nouveau nom');
});

it('refuse la mise à jour d\'un lieu à un rôle qui n\'a pas la capacité updateEvents', function (): void {
    [$organization, $doorstaff] = organizationWithVenueRole(MembershipRole::DoorStaff);
    $venue = Venue::factory()->for($organization)->create();

    app(UpdateVenue::class)->handle($venue, $doorstaff, ['name' => 'Nouveau nom']);
})->throws(AuthorizationException::class);

it('n\'expose que les lieux de l\'organisation courante', function (): void {
    $organizationA = Organization::factory()->create();
    app(CurrentOrganization::class)->set($organizationA);
    $venueA = Venue::factory()->for($organizationA)->create();

    $organizationB = Organization::factory()->create();
    app(CurrentOrganization::class)->set($organizationB);
    $venueB = Venue::factory()->for($organizationB)->create();

    $ids = Venue::query()->pluck('id');

    expect($ids)->toContain($venueB->id);
    expect($ids)->not->toContain($venueA->id);
});
