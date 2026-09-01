<?php

declare(strict_types=1);

use App\Domain\Event\Models\Event;
use App\Domain\Organization\Models\MembershipRole;
use App\Domain\Organization\Models\Organization;
use App\Models\User;
use App\Support\MultiTenancy\CurrentOrganization;

function organizationWithDuplicatorHttpRole(MembershipRole $role): array
{
    $organization = Organization::factory()->create();
    app(CurrentOrganization::class)->set($organization);

    $user = User::factory()->create();
    $user->memberships()->create(['organization_id' => $organization->id, 'role' => $role]);

    return [$organization, $user];
}

it('duplique un événement via la route dédiée et redirige vers son édition', function (): void {
    [$organization, $admin] = organizationWithDuplicatorHttpRole(MembershipRole::Admin);
    $source = Event::factory()->for($organization)->create(['title' => 'Assemblée générale 2025']);

    $response = $this->actingAs($admin)->post("/events/{$source->id}/duplicate", [
        'new_start_at' => '2026-09-08T14:30',
    ]);

    $duplicate = Event::query()->where('title', 'Assemblée générale 2025')->where('id', '!=', $source->id)->first();

    expect($duplicate)->not->toBeNull();
    $response->assertRedirect(route('events.edit', $duplicate));
});

it('refuse de dupliquer un événement d\'une autre organisation', function (): void {
    [, $admin] = organizationWithDuplicatorHttpRole(MembershipRole::Admin);

    $otherOrganization = Organization::factory()->create();
    app(CurrentOrganization::class)->set($otherOrganization);
    $otherEvent = Event::factory()->for($otherOrganization)->create();

    $response = $this->actingAs($admin)->post("/events/{$otherEvent->id}/duplicate", [
        'new_start_at' => '2026-09-08T14:30',
    ]);

    $response->assertNotFound();
});
