<?php

declare(strict_types=1);

use App\Domain\Event\Models\Event;
use App\Domain\Form\Actions\CreateForm;
use App\Domain\Form\Actions\PublishFormVersion;
use App\Domain\Form\Models\FieldOption;
use App\Domain\Form\Models\FieldType;
use App\Domain\Form\Models\FormField;
use App\Domain\Organization\Models\MembershipRole;
use App\Domain\Organization\Models\Organization;
use App\Models\User;
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

// Pas de RefreshDatabase non plus ici, pour une raison différente : ces tests
// lancent de vrais processus `php artisan` concurrents (Process::pool) pour
// prouver que le verrou Redis empêche le dépassement de capacité sous charge
// réelle (T-024). Ces processus enfants ouvrent leur propre connexion et
// commitent réellement leurs écritures ; ils resteraient invisibles pour ce
// test s'il tournait dans la transaction non commitée de RefreshDatabase.
// Chaque test nettoie donc lui-même les lignes qu'il a créées.
pest()->extend(TestCase::class)
    ->in('Concurrency');

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

/**
 * Événement publié avec un formulaire publié — la fixture de base de tous
 * les tests du parcours invité (T-031, T-033), utilisée aussi bien pour
 * confirmer un flux que pour tester une modification/annulation ensuite.
 *
 * @param  array<int, array<string, mixed>>  $fields
 * @return array{organization: Organization, event: Event}
 */
function makeGuestReadyEvent(array $fields = [], array $eventOverrides = []): array
{
    $organization = Organization::factory()->create();
    app(CurrentOrganization::class)->set($organization);

    $admin = User::factory()->create();
    $admin->memberships()->create(['organization_id' => $organization->id, 'role' => MembershipRole::Admin]);

    $event = Event::factory()->for($organization)->published()->create($eventOverrides);

    $form = app(CreateForm::class)->handle($organization, $event->id, $admin, ['name' => 'Inscription', 'fields' => $fields]);
    app(PublishFormVersion::class)->handle($form, $admin);

    app(CurrentOrganization::class)->clear();

    return ['organization' => $organization, 'event' => $event];
}
