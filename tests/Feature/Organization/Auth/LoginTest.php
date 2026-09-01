<?php

declare(strict_types=1);

use App\Models\User;

it('connecte un utilisateur avec les bons identifiants', function (): void {
    $user = User::factory()->create(['password' => 'mot-de-passe-correct']);

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'mot-de-passe-correct',
    ])->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($user);
});

it('refuse de mauvais identifiants', function (): void {
    $user = User::factory()->create(['password' => 'mot-de-passe-correct']);

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'mauvais-mot-de-passe',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('bloque la connexion 15 minutes après 5 tentatives échouées, même avec le bon mot de passe', function (): void {
    $user = User::factory()->create(['password' => 'mot-de-passe-correct']);

    for ($i = 0; $i < 5; $i++) {
        $this->post('/login', [
            'email' => $user->email,
            'password' => 'mauvais-mot-de-passe',
        ]);
    }

    $response = $this->post('/login', [
        'email' => $user->email,
        'password' => 'mot-de-passe-correct',
    ]);

    $response->assertSessionHasErrors('email');
    $this->assertGuest();
});

it('débloque la connexion après l\'expiration du verrouillage', function (): void {
    $user = User::factory()->create(['password' => 'mot-de-passe-correct']);

    for ($i = 0; $i < 5; $i++) {
        $this->post('/login', [
            'email' => $user->email,
            'password' => 'mauvais-mot-de-passe',
        ]);
    }

    $this->travel(16)->minutes();

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'mot-de-passe-correct',
    ])->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($user);
});

it('limite le débit de requêtes sur /login', function (): void {
    for ($i = 0; $i < 6; $i++) {
        $response = $this->post('/login', [
            'email' => "essai{$i}@example.com",
            'password' => 'peu-importe',
        ]);

        expect($response->status())->not->toBe(429);
    }

    $this->post('/login', [
        'email' => 'essai-final@example.com',
        'password' => 'peu-importe',
    ])->assertStatus(429);
});
