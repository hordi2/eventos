<?php

declare(strict_types=1);

use App\Domain\Organization\Actions\InviteTeamMember;
use App\Domain\Organization\Models\Membership;
use App\Domain\Organization\Models\MembershipRole;
use App\Domain\Organization\Models\Organization;
use App\Mail\TeamMemberInvitationMail;
use App\Models\User;
use App\Support\MultiTenancy\CurrentOrganization;
use Illuminate\Support\Facades\Mail;

/**
 * Invite une adresse et renvoie le chemin du lien reçu par e-mail : le jeton
 * en clair n'existe nulle part ailleurs.
 */
function inviteTeamMemberAndGetInvitationPath(Organization $organization, User $owner, string $email, MembershipRole $role = MembershipRole::Editor): string
{
    Mail::fake();

    app(CurrentOrganization::class)->set($organization);
    app(InviteTeamMember::class)->handle($organization, $owner, $email, $role);
    app(CurrentOrganization::class)->clear();

    $url = '';
    Mail::assertQueued(TeamMemberInvitationMail::class, function (TeamMemberInvitationMail $mail) use (&$url): bool {
        $url = $mail->invitationUrl;

        return true;
    });

    return (string) parse_url($url, PHP_URL_PATH);
}

it('affiche l\'invitation à une personne sans compte, sans dévoiler l\'organisation invitante', function (): void {
    ['organization' => $organization, 'doorStaff' => $owner] = makeCheckInEvent(MembershipRole::Owner);
    $path = inviteTeamMemberAndGetInvitationPath($organization, $owner, 'nouveau@example.test', MembershipRole::Admin);

    $response = $this->get($path);

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Invitations/ShowTeamMember')
        ->where('viewer', 'new-account')
        ->where('expired', false)
        ->where('roleLabel', 'Administrateur')
        ->where('nav', null));
});

it('crée le compte depuis l\'invitation, sans organisation propre, avec le rôle choisi', function (): void {
    ['organization' => $organization, 'doorStaff' => $owner] = makeCheckInEvent(MembershipRole::Owner);
    $path = inviteTeamMemberAndGetInvitationPath($organization, $owner, 'nouveau@example.test', MembershipRole::Editor);
    $organizationCount = Organization::query()->count();

    $response = $this->post("{$path}/inscription", [
        'name' => 'Grace Mbala',
        'password' => 'Motdepasse-solide-2026!',
        'password_confirmation' => 'Motdepasse-solide-2026!',
    ]);

    $response->assertRedirect(route('dashboard'));
    $user = User::query()->where('email', 'nouveau@example.test')->sole();
    $this->assertAuthenticatedAs($user);
    expect($user->hasVerifiedEmail())->toBeTrue();
    expect(Organization::query()->count())->toBe($organizationCount);

    app(CurrentOrganization::class)->set($organization);
    expect(Membership::query()->where('user_id', $user->id)->sole()->role)->toBe(MembershipRole::Editor);
    app(CurrentOrganization::class)->clear();
});

it('demande à une personne qui a déjà un compte de se connecter', function (): void {
    ['organization' => $organization, 'doorStaff' => $owner] = makeCheckInEvent(MembershipRole::Owner);
    User::factory()->create(['email' => 'existant@example.test']);
    $path = inviteTeamMemberAndGetInvitationPath($organization, $owner, 'existant@example.test');

    $this->get($path)->assertInertia(fn ($page) => $page->where('viewer', 'existing-account'));
    $this->post("{$path}/inscription", [
        'name' => 'Doublon',
        'password' => 'Motdepasse-solide-2026!',
        'password_confirmation' => 'Motdepasse-solide-2026!',
    ])->assertSessionHasErrors('email');
});

it('accepte l\'invitation avec le compte destinataire, qui garde sa propre organisation', function (): void {
    ['organization' => $organization, 'doorStaff' => $owner] = makeCheckInEvent(MembershipRole::Owner);
    ['organization' => $ownOrganization, 'doorStaff' => $invitee] = makeCheckInEvent(MembershipRole::Owner);
    $path = inviteTeamMemberAndGetInvitationPath($organization, $owner, $invitee->email, MembershipRole::Viewer);

    $response = $this->actingAs($invitee)->post("{$path}/accepter");

    $response->assertRedirect(route('dashboard'));
    $response->assertSessionHas('current_organization_id', $organization->id);
    expect(Membership::query()->where('user_id', $invitee->id)->where('organization_id', $organization->id)->sole()->role)
        ->toBe(MembershipRole::Viewer);
    expect(Membership::query()->where('user_id', $invitee->id)->where('organization_id', $ownOrganization->id)->sole()->role)
        ->toBe(MembershipRole::Owner);
});

it('promeut un collaborateur déjà invité sur des événements plutôt que de dupliquer son adhésion', function (): void {
    ['organization' => $organization, 'doorStaff' => $owner] = makeCheckInEvent(MembershipRole::Owner);
    $invitee = User::factory()->create(['email' => 'collaborateur@example.test']);
    app(CurrentOrganization::class)->set($organization);
    $invitee->memberships()->create(['organization_id' => $organization->id, 'role' => MembershipRole::Collaborator]);
    app(CurrentOrganization::class)->clear();

    $path = inviteTeamMemberAndGetInvitationPath($organization, $owner, 'collaborateur@example.test', MembershipRole::Editor);
    $this->actingAs($invitee)->post("{$path}/accepter")->assertRedirect(route('dashboard'));

    app(CurrentOrganization::class)->set($organization);
    expect(Membership::query()->where('user_id', $invitee->id)->where('organization_id', $organization->id)->sole()->role)
        ->toBe(MembershipRole::Editor);
    app(CurrentOrganization::class)->clear();
});

it('refuse d\'accepter l\'invitation depuis un autre compte', function (): void {
    ['organization' => $organization, 'doorStaff' => $owner] = makeCheckInEvent(MembershipRole::Owner);
    $path = inviteTeamMemberAndGetInvitationPath($organization, $owner, 'destinataire@example.test');
    $intruder = User::factory()->create();

    $this->actingAs($intruder)->get($path)->assertInertia(fn ($page) => $page->where('viewer', 'other-account'));
    $this->actingAs($intruder)->post("{$path}/accepter")->assertForbidden();

    app(CurrentOrganization::class)->set($organization);
    expect(Membership::query()->where('user_id', $intruder->id)->exists())->toBeFalse();
    app(CurrentOrganization::class)->clear();
});

it('refuse une invitation expirée', function (): void {
    ['organization' => $organization, 'doorStaff' => $owner] = makeCheckInEvent(MembershipRole::Owner);
    $invitee = User::factory()->create(['email' => 'tardif@example.test']);
    $path = inviteTeamMemberAndGetInvitationPath($organization, $owner, 'tardif@example.test');

    $this->travel(8)->days();

    $this->get($path)->assertInertia(fn ($page) => $page->where('expired', true));
    $this->actingAs($invitee)->post("{$path}/accepter")->assertStatus(410);
});

it('ne laisse servir le lien qu\'une seule fois', function (): void {
    ['organization' => $organization, 'doorStaff' => $owner] = makeCheckInEvent(MembershipRole::Owner);
    $invitee = User::factory()->create(['email' => 'unique@example.test']);
    $path = inviteTeamMemberAndGetInvitationPath($organization, $owner, 'unique@example.test');

    $this->actingAs($invitee)->post("{$path}/accepter")->assertRedirect();

    $this->get($path)->assertNotFound();
});

it('renvoie 404 pour un jeton inventé', function (): void {
    ['organization' => $organization] = makeCheckInEvent(MembershipRole::Owner);

    $this->get("/team-invitations/{$organization->slug}/jeton-invente")->assertNotFound();
});
