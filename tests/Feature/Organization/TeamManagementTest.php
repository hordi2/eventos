<?php

declare(strict_types=1);

use App\Domain\Organization\Models\Membership;
use App\Domain\Organization\Models\MembershipInvitation;
use App\Domain\Organization\Models\MembershipRole;
use App\Mail\TeamMemberInvitationMail;
use App\Models\User;
use App\Support\MultiTenancy\CurrentOrganization;
use Illuminate\Support\Facades\Mail;

it('invite un membre, qui apparaît en attente dans la liste', function (): void {
    Mail::fake();
    ['organization' => $organization, 'doorStaff' => $owner] = makeCheckInEvent(MembershipRole::Owner);

    $this->actingAs($owner)->post('/settings/team', [
        'email' => 'nouvel.editeur@example.test',
        'role' => 'editor',
    ])->assertSessionHas('status', 'team-member-invited');

    Mail::assertQueued(TeamMemberInvitationMail::class, fn (TeamMemberInvitationMail $mail): bool => $mail->hasTo('nouvel.editeur@example.test')
        && $mail->roleLabel === 'Éditeur');

    app(CurrentOrganization::class)->set($organization);
    $invitation = MembershipInvitation::query()->where('email', 'nouvel.editeur@example.test')->sole();
    expect($invitation->role)->toBe(MembershipRole::Editor);
    expect($invitation->status())->toBe('pending');
    app(CurrentOrganization::class)->clear();

    $this->actingAs($owner)->get('/settings/team')->assertInertia(fn ($page) => $page
        ->component('Settings/Team')
        ->has('invitations', 1)
        ->where('invitations.0.email', 'nouvel.editeur@example.test')
        ->where('invitations.0.role', 'editor'));
});

it('refuse d\'inviter quelqu\'un comme propriétaire', function (): void {
    ['doorStaff' => $owner] = makeCheckInEvent(MembershipRole::Owner);

    $this->actingAs($owner)->post('/settings/team', [
        'email' => 'futur.proprietaire@example.test',
        'role' => 'owner',
    ])->assertSessionHasErrors('role');
});

it('refuse d\'inviter une adresse déjà membre de l\'organisation', function (): void {
    ['organization' => $organization, 'doorStaff' => $owner] = makeCheckInEvent(MembershipRole::Owner);
    $existing = User::factory()->create(['email' => 'deja.membre@example.test']);
    app(CurrentOrganization::class)->set($organization);
    $existing->memberships()->create(['organization_id' => $organization->id, 'role' => MembershipRole::Editor]);
    app(CurrentOrganization::class)->clear();

    $this->actingAs($owner)->post('/settings/team', [
        'email' => 'deja.membre@example.test',
        'role' => 'admin',
    ])->assertSessionHasErrors('email');
});

it('refuse l\'invitation à un rôle qui n\'a pas la capacité inviteMembers', function (): void {
    ['doorStaff' => $editor] = makeCheckInEvent(MembershipRole::Editor);

    $this->actingAs($editor)->post('/settings/team', [
        'email' => 'quelquun@example.test',
        'role' => 'viewer',
    ])->assertForbidden();
});

it('change le rôle d\'un membre existant', function (): void {
    ['organization' => $organization, 'doorStaff' => $owner] = makeCheckInEvent(MembershipRole::Owner);
    $member = User::factory()->create();
    app(CurrentOrganization::class)->set($organization);
    $membership = $member->memberships()->create(['organization_id' => $organization->id, 'role' => MembershipRole::Viewer]);
    app(CurrentOrganization::class)->clear();

    $this->actingAs($owner)->patch("/settings/team/{$membership->id}", ['role' => 'admin'])
        ->assertSessionHas('status', 'team-member-role-updated');

    app(CurrentOrganization::class)->set($organization);
    expect($membership->fresh()->role)->toBe(MembershipRole::Admin);
    app(CurrentOrganization::class)->clear();
});

it('refuse de changer le rôle d\'un propriétaire', function (): void {
    ['organization' => $organization, 'doorStaff' => $owner] = makeCheckInEvent(MembershipRole::Owner);
    app(CurrentOrganization::class)->set($organization);
    $ownerMembership = Membership::query()->where('organization_id', $organization->id)->where('user_id', $owner->id)->sole();
    app(CurrentOrganization::class)->clear();

    $this->actingAs($owner)->patch("/settings/team/{$ownerMembership->id}", ['role' => 'admin'])
        ->assertSessionHasErrors('membership');

    app(CurrentOrganization::class)->set($organization);
    expect($ownerMembership->fresh()->role)->toBe(MembershipRole::Owner);
    app(CurrentOrganization::class)->clear();
});

it('retire un membre', function (): void {
    ['organization' => $organization, 'doorStaff' => $owner] = makeCheckInEvent(MembershipRole::Owner);
    $member = User::factory()->create();
    app(CurrentOrganization::class)->set($organization);
    $membership = $member->memberships()->create(['organization_id' => $organization->id, 'role' => MembershipRole::Editor]);
    app(CurrentOrganization::class)->clear();

    $this->actingAs($owner)->delete("/settings/team/{$membership->id}")
        ->assertSessionHas('status', 'team-member-removed');

    app(CurrentOrganization::class)->set($organization);
    expect(Membership::query()->where('id', $membership->id)->exists())->toBeFalse();
    app(CurrentOrganization::class)->clear();
});

it('refuse de retirer un propriétaire', function (): void {
    ['organization' => $organization, 'doorStaff' => $owner] = makeCheckInEvent(MembershipRole::Owner);
    app(CurrentOrganization::class)->set($organization);
    $ownerMembership = Membership::query()->where('organization_id', $organization->id)->where('user_id', $owner->id)->sole();
    app(CurrentOrganization::class)->clear();

    $this->actingAs($owner)->delete("/settings/team/{$ownerMembership->id}")
        ->assertSessionHasErrors('membership');

    app(CurrentOrganization::class)->set($organization);
    expect(Membership::query()->where('id', $ownerMembership->id)->exists())->toBeTrue();
    app(CurrentOrganization::class)->clear();
});

it('renvoie et annule une invitation en attente', function (): void {
    Mail::fake();
    ['organization' => $organization, 'doorStaff' => $owner] = makeCheckInEvent(MembershipRole::Owner);
    app(CurrentOrganization::class)->set($organization);
    $invitation = MembershipInvitation::factory()->for($organization)->create(['role' => MembershipRole::Viewer]);
    app(CurrentOrganization::class)->clear();

    $this->actingAs($owner)->post("/settings/team/invitations/{$invitation->id}/resend")
        ->assertSessionHas('status', 'team-member-invitation-resent');
    Mail::assertQueued(TeamMemberInvitationMail::class);

    $this->actingAs($owner)->delete("/settings/team/invitations/{$invitation->id}")
        ->assertSessionHas('status', 'team-member-invitation-cancelled');

    app(CurrentOrganization::class)->set($organization);
    expect(MembershipInvitation::query()->where('id', $invitation->id)->exists())->toBeFalse();
    app(CurrentOrganization::class)->clear();
});

it('cloisonne la liste d\'équipe par organisation', function (): void {
    ['organization' => $organizationA, 'doorStaff' => $ownerA] = makeCheckInEvent(MembershipRole::Owner);
    ['organization' => $organizationB] = makeCheckInEvent(MembershipRole::Owner);
    app(CurrentOrganization::class)->set($organizationB);
    MembershipInvitation::factory()->for($organizationB)->create(['email' => 'autre.organisation@example.test']);
    app(CurrentOrganization::class)->clear();

    $this->actingAs($ownerA)->get('/settings/team')->assertInertia(fn ($page) => $page
        ->where('invitations', []));
});
