<?php

declare(strict_types=1);

/*
 * Relit config/services.php avec des variables d'environnement maîtrisées,
 * indépendamment du .env du poste. Les valeurs d'origine sont restaurées :
 * les tests Feature qui suivent chargent le .env sans écraser l'existant.
 */
beforeEach(function (): void {
    $this->savedEnv = [];

    foreach (['APP_KEY', 'TICKET_QR_SECRET'] as $key) {
        $this->savedEnv[$key] = [$_SERVER[$key] ?? null, $_ENV[$key] ?? null];
    }

    $_SERVER['APP_KEY'] = $_ENV['APP_KEY'] = 'base64:cle-applicative-de-test';
});

afterEach(function (): void {
    foreach ($this->savedEnv as $key => [$server, $env]) {
        unset($_SERVER[$key], $_ENV[$key]);

        if ($server !== null) {
            $_SERVER[$key] = $server;
        }

        if ($env !== null) {
            $_ENV[$key] = $env;
        }
    }
});

it('se replie sur APP_KEY quand TICKET_QR_SECRET est déclaré mais vide', function (): void {
    $_SERVER['TICKET_QR_SECRET'] = $_ENV['TICKET_QR_SECRET'] = '';

    $services = require __DIR__.'/../../../config/services.php';

    expect($services['ticket_qr']['secret'])->toBe('base64:cle-applicative-de-test');
});

it('utilise TICKET_QR_SECRET en priorité quand il est renseigné', function (): void {
    $_SERVER['TICKET_QR_SECRET'] = $_ENV['TICKET_QR_SECRET'] = 'secret-qr-dedie';

    $services = require __DIR__.'/../../../config/services.php';

    expect($services['ticket_qr']['secret'])->toBe('secret-qr-dedie');
});
