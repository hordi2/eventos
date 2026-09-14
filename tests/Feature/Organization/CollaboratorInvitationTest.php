<?php

declare(strict_types=1);

use App\Domain\Event\Models\Event;
use App\Domain\Organization\Actions\InviteCollaborator;
use App\Domain\Organization\Models\CollaboratorPermission;
use App\Domain\Organization\Models\Membership;
use App\Domain\Organization\Models\MembershipRole;
use App\Domain\Organization\Models\Organization;
use App\Mail\CollaboratorInvitationMail;
use App\Models\User;
use App\Support\MultiTenancy\CurrentOrganization;
use Illuminate\Support\Facades\Mail;

/**
 * Invite une adresse et renvoie le chemin du lien reçu par e-mail : le jeton
 * en clair n'existe nulle part ailleurs.
 */
function inviteAndGetInvitationPath(Organization $organization, Event $event, User $owner, string $email): string
{
    Mail::fake();

    app(CurrentOrganization::class)->set($organization);
    app(InviteCollaborator::class)->handle($organization, $owner, $email, [$event->id => CollaboratorPermission::CheckIn]);
    app(CurrentOrganization::class)->clear();

    $url = '';
    Mail::assertQueued(CollaboratorInvitationMail::class, function (CollaboratorInvitationMail $mail) use (&$url): bool {
        $url = $mail->invitationUrl;

        return true;
    });

    return (string) parse_url($url, PHP_URL_PATH);
}

it('affiche l\'invitation à une personne sans compte, sans dévoiler les événements de l\'organisation', function (): void {
    ['organization' => $organization, 'event' => $event, 'doorStaff' => $owner] = makeCheckInEvent(MembershipRole::Owner);
    $path = inviteAndGetInvitationPath($organization, $event, $owner, 'nouveau@example.test');

    $response = $this->get($path);

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Invitations/Show')
        ->where('viewer', 'new-account')
        ->where('expired', false)
        ->where('events.0.title', $event->title)
        ->where('nav', null));
});

it('crée le compte depuis l\'invitation, sans organisation propre, et ouvre les événements partagés', function (): void {
    ['organization' => $organization, 'event' => $event, 'doorStaff' => $owner] = makeCheckInEvent(MembershipRole::Owner);
    $path = inviteAndGetInvitationPath($organization, $event, $owner, 'nouveau@example.test');
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
    expect(Membership::query()->where('user_id', $user->id)->sole()->role)->toBe(MembershipRole::Collaborator);

    $this->get('/dashboard')->assertInertia(fn ($page) => $page
        ->has('events', 1)
        ->where('events.0.id', $event->id)
        ->where('events.0.href', route('events.show', $event->id)));
});

it('demande à une personne qui a déjà un compte de se connecter', function (): void {
    ['organization' => $organization, 'event' => $event, 'doorStaff' => $owner] = makeCheckInEvent(MembershipRole::Owner);
    User::factory()->create(['email' => 'existant@example.test']);
    $path = inviteAndGetInvitationPath($organization, $event, $owner, 'existant@example.test');

    $this->get($path)->assertInertia(fn ($page) => $page->where('viewer', 'existing-account'));
    $this->post("{$path}/inscription", [
        'name' => 'Doublon',
        'password' => 'Motdepasse-solide-2026!',
        'password_confirmation' => 'Motdepasse-solide-2026!',
    ])->assertSessionHasErrors('email');
});

it('accepte l\'invitation avec le compte destinataire, qui garde sa propre organisation', function (): void {
    ['organization' => $organization, 'event' => $event, 'doorStaff' => $owner] = makeCheckInEvent(MembershipRole::Owner);
    ['organization' => $ownOrganization, 'doorStaff' => $invitee] = makeCheckInEvent(MembershipRole::Owner);
    $path = inviteAndGetInvitationPath($organization, $event, $owner, $invitee->email);

    $response = $this->actingAs($invitee)->post("{$path}/accepter");

    $response->assertRedirect(route('dashboard'));
    $response->assertSessionHas('current_organization_id', $organization->id);
    expect(Membership::query()->where('user_id', $invitee->id)->where('organization_id', $organization->id)->sole()->role)
        ->toBe(MembershipRole::Collaborator);
    expect(Membership::query()->where('user_id', $invitee->id)->where('organization_id', $ownOrganization->id)->sole()->role)
        ->toBe(MembershipRole::Owner);
});

it('refuse d\'accepter l\'invitation depuis un autre compte', function (): void {
    ['organization' => $organization, 'event' => $event, 'doorStaff' => $owner] = makeCheckInEvent(MembershipRole::Owner);
    $path = inviteAndGetInvitationPath($organization, $event, $owner, 'destinataire@example.test');
    $intruder = User::factory()->create();

    $this->actingAs($intruder)->get($path)->assertInertia(fn ($page) => $page->where('viewer', 'other-account'));
    $this->actingAs($intruder)->post("{$path}/accepter")->assertForbidden();

    expect(Membership::query()->where('user_id', $intruder->id)->exists())->toBeFalse();
});

it('refuse une invitation expirée', function (): void {
    ['organization' => $organization, 'event' => $event, 'doorStaff' => $owner] = makeCheckInEvent(MembershipRole::Owner);
    $invitee = User::factory()->create(['email' => 'tardif@example.test']);
    $path = inviteAndGetInvitationPath($organization, $event, $owner, 'tardif@example.test');

    $this->travel(8)->days();

    $this->get($path)->assertInertia(fn ($page) => $page->where('expired', true));
    $this->actingAs($invitee)->post("{$path}/accepter")->assertStatus(410);
});

it('ne laisse servir le lien qu\'une seule fois', function (): void {
    ['organization' => $organization, 'event' => $event, 'doorStaff' => $owner] = makeCheckInEvent(MembershipRole::Owner);
    $invitee = User::factory()->create(['email' => 'unique@example.test']);
    $path = inviteAndGetInvitationPath($organization, $event, $owner, 'unique@example.test');

    $this->actingAs($invitee)->post("{$path}/accepter")->assertRedirect();

    $this->get($path)->assertNotFound();
});

it('renvoie 404 pour un jeton inventé', function (): void {
    ['organization' => $organization] = makeCheckInEvent(MembershipRole::Owner);

    $this->get("/invitations/{$organization->slug}/jeton-invente")->assertNotFound();
});
