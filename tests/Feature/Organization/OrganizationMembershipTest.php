<?php

declare(strict_types=1);

use App\Domain\Organization\Models\MembershipRole;
use App\Domain\Organization\Models\Organization;
use App\Models\User;

it('permet à un utilisateur d\'appartenir à plusieurs organisations', function (): void {
    $user = User::factory()->create();
    $organizationA = Organization::factory()->create();
    $organizationB = Organization::factory()->create();

    $user->memberships()->create([
        'organization_id' => $organizationA->id,
        'role' => MembershipRole::Owner,
    ]);
    $user->memberships()->create([
        'organization_id' => $organizationB->id,
        'role' => MembershipRole::Editor,
    ]);

    expect($user->organizations()->pluck('organizations.id')->all())
        ->toEqualCanonicalizing([$organizationA->id, $organizationB->id]);
});

it('conserve le rôle de chaque adhésion', function (): void {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();

    $membership = $user->memberships()->create([
        'organization_id' => $organization->id,
        'role' => MembershipRole::DoorStaff,
    ]);

    expect($membership->fresh()->role)->toBe(MembershipRole::DoorStaff);
});
