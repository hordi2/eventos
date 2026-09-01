<?php

declare(strict_types=1);

use App\Support\MultiTenancy\CurrentOrganization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

// Le contexte "organisation courante" est aussi propagé au niveau de la
// session PostgreSQL (set_config). La connexion étant réutilisée d'un test
// à l'autre, on la réinitialise systématiquement pour éviter toute fuite.
afterEach(function (): void {
    if (app()->bound(CurrentOrganization::class)) {
        app(CurrentOrganization::class)->clear();
    }
})->in('Feature');
