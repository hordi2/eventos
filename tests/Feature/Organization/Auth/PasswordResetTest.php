<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;

it('envoie un lien de réinitialisation', function (): void {
    $user = User::factory()->create();

    $this->post('/forgot-password', ['email' => $user->email])
        ->assertSessionHas('status');
});

it('réinitialise le mot de passe avec un jeton valide', function (): void {
    $user = User::factory()->create();
    $token = Password::createToken($user);

    $this->post('/reset-password', [
        'token' => $token,
        'email' => $user->email,
        'password' => 'nouveau-mot-de-passe',
        'password_confirmation' => 'nouveau-mot-de-passe',
    ])->assertRedirect(route('login'));

    expect(Hash::check('nouveau-mot-de-passe', $user->fresh()->password))->toBeTrue();
});

it('refuse un jeton de réinitialisation expiré après 30 minutes', function (): void {
    $user = User::factory()->create();
    $token = Password::createToken($user);

    $this->travel(31)->minutes();

    $this->post('/reset-password', [
        'token' => $token,
        'email' => $user->email,
        'password' => 'nouveau-mot-de-passe',
        'password_confirmation' => 'nouveau-mot-de-passe',
    ])->assertSessionHasErrors('email');

    expect(Hash::check('nouveau-mot-de-passe', $user->fresh()->password))->toBeFalse();
});

it('accepte encore un jeton de réinitialisation à 29 minutes', function (): void {
    $user = User::factory()->create();
    $token = Password::createToken($user);

    $this->travel(29)->minutes();

    $this->post('/reset-password', [
        'token' => $token,
        'email' => $user->email,
        'password' => 'nouveau-mot-de-passe',
        'password_confirmation' => 'nouveau-mot-de-passe',
    ])->assertRedirect(route('login'));

    expect(Hash::check('nouveau-mot-de-passe', $user->fresh()->password))->toBeTrue();
});
