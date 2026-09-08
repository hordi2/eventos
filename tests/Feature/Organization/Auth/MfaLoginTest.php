<?php

declare(strict_types=1);

use App\Domain\Organization\Models\MembershipRole;
use App\Domain\Organization\Models\Organization;
use App\Mail\MfaCodeMail;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

function extractMfaCode(MfaCodeMail $mail): string
{
    preg_match('/(\d{6})/', $mail->render(), $matches);

    return $matches[1];
}

it('exige un code MFA quand l\'utilisateur l\'a activé, sans authentifier immédiatement', function (): void {
    Mail::fake();
    $user = User::factory()->create(['password' => 'mot-de-passe-correct', 'mfa_email_enabled' => true]);

    $response = $this->post('/login', ['email' => $user->email, 'password' => 'mot-de-passe-correct']);

    $response->assertRedirect(route('login.mfa.show'));
    $this->assertGuest();
    Mail::assertQueued(MfaCodeMail::class);
});

it('authentifie après un code MFA valide', function (): void {
    Mail::fake();
    $user = User::factory()->create(['password' => 'mot-de-passe-correct', 'mfa_email_enabled' => true]);

    $this->post('/login', ['email' => $user->email, 'password' => 'mot-de-passe-correct']);

    $sentCode = null;
    Mail::assertQueued(MfaCodeMail::class, function (MfaCodeMail $mail) use (&$sentCode) {
        $sentCode = extractMfaCode($mail);

        return true;
    });

    $response = $this->post('/login/mfa', ['code' => $sentCode]);

    $response->assertRedirect(route('dashboard'));
    $this->assertAuthenticatedAs($user);
});

it('refuse un code MFA incorrect', function (): void {
    Mail::fake();
    $user = User::factory()->create(['password' => 'mot-de-passe-correct', 'mfa_email_enabled' => true]);

    $this->post('/login', ['email' => $user->email, 'password' => 'mot-de-passe-correct']);

    $response = $this->post('/login/mfa', ['code' => '000000']);

    $response->assertSessionHasErrors('code');
    $this->assertGuest();
});

it('exige un code MFA quand l\'organisation l\'impose, même sans réglage personnel', function (): void {
    Mail::fake();
    $organization = Organization::factory()->create(['require_mfa_for_members' => true]);
    $user = User::factory()->create(['password' => 'mot-de-passe-correct']);
    $user->memberships()->create(['organization_id' => $organization->id, 'role' => MembershipRole::Editor]);

    $response = $this->post('/login', ['email' => $user->email, 'password' => 'mot-de-passe-correct']);

    $response->assertRedirect(route('login.mfa.show'));
    $this->assertGuest();
});

it('ne demande pas de code MFA quand ni l\'utilisateur ni son organisation ne l\'exigent', function (): void {
    Mail::fake();
    $user = User::factory()->create(['password' => 'mot-de-passe-correct']);

    $response = $this->post('/login', ['email' => $user->email, 'password' => 'mot-de-passe-correct']);

    $response->assertRedirect(route('dashboard'));
    $this->assertAuthenticatedAs($user);
    Mail::assertNotQueued(MfaCodeMail::class);
});
