<?php

declare(strict_types=1);

use App\Domain\Form\Models\FieldOption;
use App\Domain\Form\Models\FieldType;
use App\Domain\Form\Models\FormField;
use App\Support\MultiTenancy\CurrentOrganization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

// Pas de RefreshDatabase ici : les tests Unit ne touchent jamais la base.
// L'application est tout de même démarrée (Facade::setFacadeApplication)
// pour que des classes comme FieldValidationRules puissent s'appuyer sur
// Validator::make() sans réimplémenter la validation Laravel.
pest()->extend(TestCase::class)
    ->in('Unit');

// Le contexte "organisation courante" est aussi propagé au niveau de la
// session PostgreSQL (set_config). La connexion étant réutilisée d'un test
// à l'autre, on la réinitialise systématiquement pour éviter toute fuite.
afterEach(function (): void {
    if (app()->bound(CurrentOrganization::class)) {
        app(CurrentOrganization::class)->clear();
    }
})->in('Feature');

/**
 * Construit un FormField en mémoire (jamais persisté) pour les tests Unit du
 * moteur de types de champs (T-021) : ces classes ne dépendent que des
 * attributs du champ, pas de la base ni du multi-tenant.
 *
 * @param  list<array<string, mixed>>  $options
 */
function makeField(FieldType $type, array $overrides = [], array $options = []): FormField
{
    $field = new FormField(array_merge([
        'key' => 'test_field',
        'type' => $type,
        'label' => 'Champ de test',
        'is_required' => false,
    ], $overrides));

    $field->setRelation(
        'options',
        collect($options)->map(fn (array $option, int $i): FieldOption => new FieldOption(array_merge(['position' => $i], $option))),
    );

    return $field;
}
