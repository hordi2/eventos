<?php

declare(strict_types=1);

use App\Support\Status\Models\SystemStatusCheck;

it('affiche la page de statut publique sans authentification, même sans historique', function (): void {
    $this->get('/status')
        ->assertOk()
        ->assertSee('Tous les systèmes sont opérationnels');
});

it('affiche un incident en cours d\'après la dernière vérification', function (): void {
    SystemStatusCheck::query()->create([
        'checked_at' => now(),
        'is_healthy' => false,
        'components' => ['database' => false, 'redis' => true],
    ]);

    $this->get('/status')
        ->assertOk()
        ->assertSee('Un incident est en cours')
        ->assertSee('En échec');
});
