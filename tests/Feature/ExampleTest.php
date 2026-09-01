<?php

declare(strict_types=1);

it('retourne une réponse réussie sur la page d\'accueil', function () {
    $response = $this->get('/');

    $response->assertStatus(200);
});
