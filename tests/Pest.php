<?php

declare(strict_types=1);

use App\Domain\Contact\Models\Contact;
use App\Domain\Event\Models\Event;
use App\Domain\Form\Actions\CreateForm;
use App\Domain\Form\Actions\PublishFormVersion;
use App\Domain\Form\Models\Attendee;
use App\Domain\Form\Models\FieldOption;
use App\Domain\Form\Models\FieldType;
use App\Domain\Form\Models\Form;
use App\Domain\Form\Models\FormField;
use App\Domain\Form\Models\FormVersion;
use App\Domain\Form\Models\Registration;
use App\Domain\Form\Models\RegistrationStatus;
use App\Domain\Organization\Models\MembershipRole;
use App\Domain\Organization\Models\Organization;
use App\Models\User;
use App\Support\MultiTenancy\CurrentOrganization;
use Carbon\CarbonImmutable;
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

// Hors des testsuites de phpunit.xml, comme Concurrency : ces tests
// traitent un vrai volume (10 000 lignes, T-041) et prennent plusieurs
// dizaines de secondes — assez pour ralentir sensiblement le
// ./vendor/bin/pest par défaut de chaque ticket s'ils y restaient.
// RefreshDatabase reste pertinent ici (contrairement à Concurrency) :
// aucun processus enfant, tout se passe dans la même connexion.
pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Performance');

// Le contexte "organisation courante" est aussi propagé au niveau de la
// session PostgreSQL (set_config). La connexion étant réutilisée d'un test
// à l'autre, on la réinitialise systématiquement pour éviter toute fuite.
afterEach(function (): void {
    if (app()->bound(CurrentOrganization::class)) {
        app(CurrentOrganization::class)->clear();
    }
})->in('Feature', 'Performance');

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

/**
 * Une organisation avec un unique membre du rôle donné — même fixture que
 * organizationWithVenueRole/organizationWithFormBuilderRole, factorisée ici
 * car les tests Contact (T-040) la déclinent sur trois fichiers.
 *
 * @return array{0: Organization, 1: User}
 */
function organizationWithContactRole(MembershipRole $role): array
{
    $organization = Organization::factory()->create();
    app(CurrentOrganization::class)->set($organization);

    $user = User::factory()->create();
    $user->memberships()->create(['organization_id' => $organization->id, 'role' => $role]);

    return [$organization, $user];
}

/**
 * Construit une inscription réelle pour un contact donné, avec son
 * Attendee principal (T-042, réutilisé par T-045) — event_id/form_version_id
 * sont des clés étrangères réelles : chaque niveau doit explicitement
 * partager la même organisation, sans quoi les factories imbriquées en
 * créeraient chacune une nouvelle, rejetée par la RLS (même gotcha que
 * ConfirmPromotedRegistrationTest).
 */
function registerContactForEvent(
    Organization $organization,
    Event $event,
    Contact $contact,
    RegistrationStatus $status,
    ?CarbonImmutable $checkedInAt = null,
): Registration {
    $form = Form::factory()->create([
        'organization_id' => $organization->id,
        'event_id' => $event->id,
        'created_by' => User::factory()->create()->id,
    ]);
    $version = FormVersion::factory()->create(['organization_id' => $organization->id, 'form_id' => $form->id]);

    $registration = Registration::factory()->create([
        'organization_id' => $organization->id,
        'event_id' => $event->id,
        'form_version_id' => $version->id,
        'contact_id' => $contact->id,
        'status' => $status,
    ]);

    Attendee::factory()->create([
        'organization_id' => $organization->id,
        'registration_id' => $registration->id,
        'is_primary' => true,
        'checked_in_at' => $checkedInAt,
    ]);

    return $registration;
}

/**
 * Organisation prête pour un envoi (e-mail ou WhatsApp), sans membre —
 * T-043/T-045, partagée entre SendEmailTest et SendWhatsappTest.
 */
function makeOrganizationForMessaging(): Organization
{
    $organization = Organization::factory()->create();
    app(CurrentOrganization::class)->set($organization);

    return $organization;
}
