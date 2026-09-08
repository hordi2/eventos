<?php

declare(strict_types=1);

use App\Domain\Organization\Models\MembershipRole;
use Illuminate\Support\Facades\Hash;

it('affiche la page sécurité avec le réglage MFA personnel', function (): void {
    ['doorStaff' => $user] = makeCheckInEvent(MembershipRole::Owner);

    $response = $this->actingAs($user)->get('/settings/security');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('mfaEmailEnabled', false)
        ->where('organizationMfa.requireMfaForMembers', false));
});

it('n\'expose pas le réglage MFA d\'organisation à un rôle sans manageSecurity', function (): void {
    ['doorStaff' => $editor] = makeCheckInEvent(MembershipRole::Editor);

    $response = $this->actingAs($editor)->get('/settings/security');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->where('organizationMfa', null));
});

it('change le mot de passe avec le mot de passe actuel correct', function (): void {
    ['doorStaff' => $user] = makeCheckInEvent(MembershipRole::Owner);

    $response = $this->actingAs($user)->put('/settings/security/password', [
        'current_password' => 'password',
        'password' => 'un-nouveau-mot-de-passe-solide',
        'password_confirmation' => 'un-nouveau-mot-de-passe-solide',
    ]);

    $response->assertRedirect();
    expect(Hash::check('un-nouveau-mot-de-passe-solide', $user->fresh()->password))->toBeTrue();
});

it('refuse le changement de mot de passe avec un mot de passe actuel incorrect', function (): void {
    ['doorStaff' => $user] = makeCheckInEvent(MembershipRole::Owner);

    $response = $this->actingAs($user)->put('/settings/security/password', [
        'current_password' => 'mauvais-mot-de-passe',
        'password' => 'un-nouveau-mot-de-passe-solide',
        'password_confirmation' => 'un-nouveau-mot-de-passe-solide',
    ]);

    $response->assertSessionHasErrors('current_password');
});

it('active la MFA personnelle', function (): void {
    ['doorStaff' => $user] = makeCheckInEvent(MembershipRole::Owner);

    $this->actingAs($user)->patch('/settings/security/mfa', ['mfa_email_enabled' => true])->assertRedirect();

    expect($user->fresh()->mfa_email_enabled)->toBeTrue();
});

it('un propriétaire peut exiger la MFA pour tous les membres de l\'organisation', function (): void {
    ['organization' => $organization, 'doorStaff' => $owner] = makeCheckInEvent(MembershipRole::Owner);

    $this->actingAs($owner)->patch('/settings/security/organization-mfa', ['require_mfa_for_members' => true])->assertRedirect();

    expect($organization->fresh()->require_mfa_for_members)->toBeTrue();
});

it('refuse la politique MFA d\'organisation à un rôle sans manageSecurity', function (): void {
    ['doorStaff' => $editor] = makeCheckInEvent(MembershipRole::Editor);

    $response = $this->actingAs($editor)->patch('/settings/security/organization-mfa', ['require_mfa_for_members' => true]);

    $response->assertForbidden();
});
