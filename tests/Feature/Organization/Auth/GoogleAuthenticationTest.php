<?php

declare(strict_types=1);

use App\Models\User;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

function fakeGoogleUser(string $email, string $name): SocialiteUser
{
    $googleUser = new SocialiteUser;
    $googleUser->map(['id' => '1234567890', 'name' => $name, 'email' => $email]);

    return $googleUser;
}

it('crée un compte et une organisation à la volée lors d\'une première connexion Google', function (): void {
    Socialite::shouldReceive('driver->user')
        ->once()
        ->andReturn(fakeGoogleUser('jane@example.com', 'Jane Doe'));

    $this->get('/auth/google/callback')->assertRedirect(route('dashboard'));

    $user = User::query()->where('email', 'jane@example.com')->firstOrFail();
    $this->assertAuthenticatedAs($user);
    expect($user->hasVerifiedEmail())->toBeTrue();
    expect($user->organizations()->count())->toBe(1);
});

it('connecte un compte Google existant sans en recréer un nouveau', function (): void {
    $user = User::factory()->create(['email' => 'jane@example.com']);

    Socialite::shouldReceive('driver->user')
        ->once()
        ->andReturn(fakeGoogleUser('jane@example.com', 'Jane Doe'));

    $this->get('/auth/google/callback')->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($user);
    expect(User::query()->where('email', 'jane@example.com')->count())->toBe(1);
});
