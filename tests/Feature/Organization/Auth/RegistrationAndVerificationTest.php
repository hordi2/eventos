<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\URL;

it('permet le parcours complet inscription puis vérification puis accès au tableau de bord', function (): void {
    $this->post('/register', [
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'password' => 'mot-de-passe-solide',
        'password_confirmation' => 'mot-de-passe-solide',
        'organization_name' => 'Jane Events',
    ])->assertRedirect(route('dashboard'));

    $user = User::query()->where('email', 'jane@example.com')->firstOrFail();
    $this->assertAuthenticatedAs($user);
    expect($user->organizations()->count())->toBe(1);
    expect($user->hasVerifiedEmail())->toBeFalse();

    // Tant que l'e-mail n'est pas vérifié, le tableau de bord redirige.
    $this->get('/dashboard')->assertRedirect(route('verification.notice'));

    $verifyUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1($user->email)],
    );

    $this->get($verifyUrl)->assertRedirect();
    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();

    $this->get('/dashboard')->assertOk();
});

it('hache le mot de passe en argon2id', function (): void {
    $this->post('/register', [
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'password' => 'mot-de-passe-solide',
        'password_confirmation' => 'mot-de-passe-solide',
        'organization_name' => 'Jane Events',
    ]);

    $user = User::query()->where('email', 'jane@example.com')->firstOrFail();

    expect(password_get_info($user->password)['algoName'])->toBe('argon2id');
});

it('limite le débit de requêtes sur /register', function (): void {
    // Mot de passe non confirmé : la validation échoue systématiquement, donc
    // aucune inscription n'aboutit et l'utilisateur reste "invité" tout du
    // long — sinon la 1ère inscription réussie connecterait l'utilisateur et
    // le middleware "guest" court-circuiterait les tentatives suivantes
    // avant même d'atteindre le throttle.
    for ($i = 0; $i < 6; $i++) {
        $response = $this->post('/register', [
            'name' => 'Test',
            'email' => "test{$i}@example.com",
            'password' => 'mot-de-passe-solide',
            'password_confirmation' => 'ne-correspond-pas',
            'organization_name' => 'Test Org',
        ]);

        expect($response->status())->not->toBe(429);
    }

    $this->post('/register', [
        'name' => 'Test',
        'email' => 'test-final@example.com',
        'password' => 'mot-de-passe-solide',
        'password_confirmation' => 'ne-correspond-pas',
        'organization_name' => 'Test Org',
    ])->assertStatus(429);
});
