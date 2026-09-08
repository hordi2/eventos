<?php

declare(strict_types=1);

use App\Domain\Organization\Models\Membership;
use App\Domain\Organization\Models\MembershipRole;
use App\Domain\Organization\Models\Organization;
use App\Models\User;
use App\Support\MultiTenancy\CurrentOrganization;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;

/**
 * Ajoute un second Owner à l'organisation créée par makeCheckInEvent(),
 * pour tester le cas "l'utilisateur n'est PAS seul propriétaire" — même
 * piège CurrentOrganization que registerContactForEvent() (tests/Pest.php).
 */
function addSecondOwner(Organization $organization): void
{
    app(CurrentOrganization::class)->set($organization);
    Membership::factory()->for($organization)->create(['role' => MembershipRole::Owner]);
    app(CurrentOrganization::class)->clear();
}

it('affiche le profil courant', function (): void {
    ['doorStaff' => $user] = makeCheckInEvent(MembershipRole::Owner);

    $response = $this->actingAs($user)->get('/settings/profile');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('user.email', $user->email)
        ->where('isSoleOrganizationOwner', true));
});

it('met à jour le nom sans toucher à la vérification quand l\'e-mail ne change pas', function (): void {
    ['doorStaff' => $user] = makeCheckInEvent(MembershipRole::Owner);
    $user->forceFill(['email_verified_at' => now()])->save();

    $response = $this->actingAs($user)->patch('/settings/profile', [
        'name' => 'Nouveau nom',
        'email' => $user->email,
    ]);

    $response->assertRedirect();
    $fresh = $user->fresh();
    expect($fresh->name)->toBe('Nouveau nom');
    expect($fresh->hasVerifiedEmail())->toBeTrue();
});

it('remet l\'e-mail à vérifier et envoie une notification quand l\'e-mail change', function (): void {
    Notification::fake();
    ['doorStaff' => $user] = makeCheckInEvent(MembershipRole::Owner);
    $user->forceFill(['email_verified_at' => now()])->save();

    $this->actingAs($user)->patch('/settings/profile', [
        'name' => $user->name,
        'email' => 'nouvelle-adresse@example.com',
    ]);

    $fresh = $user->fresh();
    expect($fresh->email)->toBe('nouvelle-adresse@example.com');
    expect($fresh->hasVerifiedEmail())->toBeFalse();
    Notification::assertSentTo($fresh, VerifyEmail::class);
});

it('refuse un e-mail déjà utilisé par un autre compte', function (): void {
    ['doorStaff' => $user] = makeCheckInEvent(MembershipRole::Owner);
    $other = User::factory()->create(['email' => 'deja-pris@example.com']);

    $response = $this->actingAs($user)->patch('/settings/profile', [
        'name' => $user->name,
        'email' => $other->email,
    ]);

    $response->assertSessionHasErrors('email');
});

it('supprime (anonymise) le compte quand l\'utilisateur n\'est pas seul propriétaire', function (): void {
    ['organization' => $organization, 'doorStaff' => $owner] = makeCheckInEvent(MembershipRole::Owner);
    addSecondOwner($organization);

    $response = $this->actingAs($owner)->delete('/settings/profile', ['password' => 'password']);

    $response->assertRedirect(route('login'));
    $this->assertGuest();
    $fresh = User::withTrashed()->findOrFail($owner->id);
    expect($fresh->trashed())->toBeTrue();
    expect($fresh->name)->toBe('Compte supprimé');
});

it('bloque la suppression de compte quand l\'utilisateur est seul propriétaire d\'une organisation', function (): void {
    ['doorStaff' => $owner] = makeCheckInEvent(MembershipRole::Owner);

    $response = $this->actingAs($owner)->delete('/settings/profile', ['password' => 'password']);

    $response->assertSessionHasErrors('password');
    $this->assertAuthenticated();
    expect($owner->fresh()->trashed())->toBeFalse();
});

it('refuse la suppression de compte avec un mot de passe incorrect', function (): void {
    ['organization' => $organization, 'doorStaff' => $owner] = makeCheckInEvent(MembershipRole::Owner);
    addSecondOwner($organization);

    $response = $this->actingAs($owner)->delete('/settings/profile', ['password' => 'mauvais-mot-de-passe']);

    $response->assertSessionHasErrors('password');
    expect($owner->fresh()->trashed())->toBeFalse();
});
