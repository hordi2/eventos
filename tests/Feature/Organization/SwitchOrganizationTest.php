<?php

declare(strict_types=1);

use App\Domain\Organization\Models\CollaboratorPermission;
use App\Domain\Organization\Models\Membership;
use App\Domain\Organization\Models\MembershipRole;

it('bascule vers une organisation dont on est membre', function (): void {
    ['organization' => $sharingOrganization, 'event' => $sharedEvent] = makeCheckInEvent(MembershipRole::Owner);
    $user = makeEventCollaborator($sharingOrganization, $sharedEvent, CollaboratorPermission::CheckIn);
    ['organization' => $ownOrganization] = makeCheckInEvent(MembershipRole::Owner);
    Membership::factory()->for($ownOrganization)->for($user)->create(['role' => MembershipRole::Owner]);

    $response = $this->actingAs($user)->post("/organizations/{$sharingOrganization->id}/switch");

    $response->assertRedirect(route('dashboard'));
    $response->assertSessionHas('current_organization_id', $sharingOrganization->id);
    $this->actingAs($user)->get('/dashboard')->assertInertia(fn ($page) => $page
        ->has('organizations', 2)
        ->where('events.0.id', $sharedEvent->id));
});

it('refuse de basculer vers une organisation dont on n\'est pas membre', function (): void {
    ['doorStaff' => $owner] = makeCheckInEvent(MembershipRole::Owner);
    ['organization' => $foreignOrganization] = makeCheckInEvent(MembershipRole::Owner);

    $this->actingAs($owner)->post("/organizations/{$foreignOrganization->id}/switch")->assertNotFound();
});

it('ouvre son propre espace avant celui où l\'on n\'est que collaborateur', function (): void {
    ['organization' => $sharingOrganization, 'event' => $sharedEvent] = makeCheckInEvent(MembershipRole::Owner);
    $user = makeEventCollaborator($sharingOrganization, $sharedEvent, CollaboratorPermission::Administrator);
    ['organization' => $ownOrganization] = makeCheckInEvent(MembershipRole::Owner);
    Membership::factory()->for($ownOrganization)->for($user)->create(['role' => MembershipRole::Owner]);

    $this->actingAs($user)->get('/dashboard')->assertInertia(fn ($page) => $page
        ->where('canCreateEvents', true)
        ->where('organizations', fn ($organizations): bool => collect($organizations)->firstWhere('current', true)['id'] === $ownOrganization->id));
});
