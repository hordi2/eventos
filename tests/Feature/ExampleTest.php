<?php

declare(strict_types=1);

it('redirige un visiteur non authentifié vers la connexion', function () {
    $response = $this->get('/');

    $response->assertRedirect(route('login'));
});
