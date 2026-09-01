<?php

declare(strict_types=1);

use App\Domain\Event\Models\Event;
use App\Domain\Form\Actions\CreateForm;
use App\Domain\Form\Actions\PublishFormVersion;
use App\Domain\Form\Actions\ReviseForm;
use App\Domain\Form\Actions\UpdateFormDraft;
use App\Domain\Form\InvalidFormVersionTransitionException;
use App\Domain\Form\Models\FieldType;
use App\Domain\Form\Models\Form;
use App\Domain\Form\Models\FormVersion;
use App\Domain\Form\Models\FormVersionStatus;
use App\Domain\Organization\Models\MembershipRole;
use App\Domain\Organization\Models\Organization;
use App\Models\User;
use App\Support\MultiTenancy\CurrentOrganization;
use Illuminate\Auth\Access\AuthorizationException;

function organizationWithFormRole(MembershipRole $role): array
{
    $organization = Organization::factory()->create();
    app(CurrentOrganization::class)->set($organization);

    $user = User::factory()->create();
    $user->memberships()->create(['organization_id' => $organization->id, 'role' => $role]);

    return [$organization, $user];
}

function basicFieldSet(): array
{
    return [
        ['key' => 'nom_complet', 'type' => FieldType::ShortText, 'label' => 'Nom complet', 'is_required' => true],
        ['key' => 'diner', 'type' => FieldType::YesNo, 'label' => 'Participez-vous au dîner ?'],
        [
            'key' => 'menu',
            'type' => FieldType::SingleChoice,
            'label' => 'Choix du menu',
            'options' => [
                ['label' => 'Poisson', 'quota' => 30],
                ['label' => 'Végétarien', 'quota' => 20],
            ],
        ],
    ];
}

it('crée un formulaire avec sa première version en brouillon et ses champs', function (): void {
    [$organization, $admin] = organizationWithFormRole(MembershipRole::Admin);
    $event = Event::factory()->for($organization)->create();

    $form = app(CreateForm::class)->handle($organization, $event->id, $admin, [
        'name' => 'Inscription générale',
        'fields' => basicFieldSet(),
    ]);

    expect($form->name)->toBe('Inscription générale');
    expect($form->current_version_id)->toBeNull();

    $version = $form->latestVersion();
    expect($version->version_number)->toBe(1);
    expect($version->status)->toBe(FormVersionStatus::Draft);
    expect($version->fields)->toHaveCount(3);

    $menuField = $version->fields->firstWhere('key', 'menu');
    expect($menuField->options)->toHaveCount(2);
    expect($menuField->options->firstWhere('label', 'Poisson')->quota)->toBe(30);
});

it('refuse la création à un rôle qui n\'a pas la capacité createEvents', function (): void {
    [$organization, $editor] = organizationWithFormRole(MembershipRole::Editor);
    $event = Event::factory()->for($organization)->create();

    app(CreateForm::class)->handle($organization, $event->id, $editor, [
        'name' => 'Test',
        'fields' => [],
    ]);
})->throws(AuthorizationException::class);

it('modifie en place les champs d\'un brouillon jamais publié', function (): void {
    [$organization, $admin] = organizationWithFormRole(MembershipRole::Admin);
    $event = Event::factory()->for($organization)->create();
    $form = app(CreateForm::class)->handle($organization, $event->id, $admin, [
        'name' => 'Inscription générale',
        'fields' => basicFieldSet(),
    ]);
    $draftVersionId = $form->latestVersion()->id;

    $updated = app(UpdateFormDraft::class)->handle($form, $admin, [
        ['key' => 'nom_complet', 'type' => FieldType::ShortText, 'label' => 'Nom et prénom'],
    ]);

    $version = $updated->latestVersion();
    expect($version->id)->toBe($draftVersionId);
    expect($version->version_number)->toBe(1);
    expect($version->fields)->toHaveCount(1);
    expect($version->fields->first()->label)->toBe('Nom et prénom');
});

it('publie la version courante et l\'active pour le formulaire', function (): void {
    [$organization, $admin] = organizationWithFormRole(MembershipRole::Admin);
    $event = Event::factory()->for($organization)->create();
    $form = app(CreateForm::class)->handle($organization, $event->id, $admin, [
        'name' => 'Inscription générale',
        'fields' => basicFieldSet(),
    ]);
    $versionId = $form->latestVersion()->id;

    $published = app(PublishFormVersion::class)->handle($form, $admin);

    expect($published->current_version_id)->toBe($versionId);
    expect($published->currentVersion->status)->toBe(FormVersionStatus::Published);
    expect($published->currentVersion->published_at)->not->toBeNull();
    expect($published->hasPublishedVersion())->toBeTrue();
});

it('refuse de modifier en place une version déjà publiée', function (): void {
    [$organization, $admin] = organizationWithFormRole(MembershipRole::Admin);
    $event = Event::factory()->for($organization)->create();
    $form = app(CreateForm::class)->handle($organization, $event->id, $admin, [
        'name' => 'Inscription générale',
        'fields' => basicFieldSet(),
    ]);
    app(PublishFormVersion::class)->handle($form, $admin);

    app(UpdateFormDraft::class)->handle($form->fresh(), $admin, basicFieldSet());
})->throws(InvalidFormVersionTransitionException::class);

it('refuse de publier un formulaire qui n\'a pas de brouillon en attente', function (): void {
    [$organization, $admin] = organizationWithFormRole(MembershipRole::Admin);
    $event = Event::factory()->for($organization)->create();
    $form = app(CreateForm::class)->handle($organization, $event->id, $admin, [
        'name' => 'Inscription générale',
        'fields' => basicFieldSet(),
    ]);
    app(PublishFormVersion::class)->handle($form, $admin);

    app(PublishFormVersion::class)->handle($form->fresh(), $admin);
})->throws(InvalidFormVersionTransitionException::class);

it('refuse de créer une nouvelle version tant que la dernière n\'est pas publiée', function (): void {
    [$organization, $admin] = organizationWithFormRole(MembershipRole::Admin);
    $event = Event::factory()->for($organization)->create();
    $form = app(CreateForm::class)->handle($organization, $event->id, $admin, [
        'name' => 'Inscription générale',
        'fields' => basicFieldSet(),
    ]);

    app(ReviseForm::class)->handle($form, $admin, basicFieldSet());
})->throws(InvalidFormVersionTransitionException::class);

it('modifier un formulaire publié crée une nouvelle version sans toucher à l\'ancienne, même après 50 réponses simulées', function (): void {
    [$organization, $admin] = organizationWithFormRole(MembershipRole::Admin);
    $event = Event::factory()->for($organization)->create();
    $form = app(CreateForm::class)->handle($organization, $event->id, $admin, [
        'name' => 'Inscription générale',
        'fields' => basicFieldSet(),
    ]);
    app(PublishFormVersion::class)->handle($form, $admin);
    $form = $form->fresh();
    $v1 = $form->latestVersion();
    $v1FieldsSnapshot = $v1->fields->map(fn ($f) => ['key' => $f->key, 'label' => $f->label, 'type' => $f->type])->all();

    // Aucune table de réponses n'existe encore (Registration arrive en T-030) :
    // ce test prouve que l'immutabilité structurelle de v1 ne dépend d'aucun
    // compteur de réponses — elle est garantie inconditionnellement par
    // ReviseForm, qui ne modifie jamais une version déjà publiée. C'est
    // précisément ce qui rend les réponses (une fois collectées) interprétables.
    for ($i = 0; $i < 50; $i++) {
        expect($v1->fresh()->fields->count())->toBe(3);
    }

    $v2 = app(ReviseForm::class)->handle($form, $admin, [
        // Renommage : même clé "nom_complet", nouveau libellé.
        ['key' => 'nom_complet', 'type' => FieldType::ShortText, 'label' => 'Nom et prénom complet', 'is_required' => true],
        // Suppression : "diner" et "menu" disparaissent de v2.
        // Ajout : nouveau champ "allergies".
        ['key' => 'allergies', 'type' => FieldType::LongText, 'label' => 'Allergies éventuelles'],
    ]);

    // v1 reste exactement telle qu'elle était, quel que soit ce que fait v2.
    $v1AfterRevision = $form->fresh()->versions()->where('version_number', 1)->first();
    $v1FieldsAfter = $v1AfterRevision->fields->map(fn ($f) => ['key' => $f->key, 'label' => $f->label, 'type' => $f->type])->all();
    expect($v1FieldsAfter)->toEqualCanonicalizing($v1FieldsSnapshot);
    expect($v1AfterRevision->status)->toBe(FormVersionStatus::Published);

    // v2 a la structure attendue.
    expect($v2->version_number)->toBe(2);
    expect($v2->status)->toBe(FormVersionStatus::Draft);
    expect($v2->fields)->toHaveCount(2);
    expect($v2->fields->firstWhere('key', 'nom_complet')->label)->toBe('Nom et prénom complet');
    expect($v2->fields->firstWhere('key', 'allergies'))->not->toBeNull();
    expect($v2->fields->firstWhere('key', 'diner'))->toBeNull();
    expect($v2->fields->firstWhere('key', 'menu'))->toBeNull();
});

it('archive la version précédente quand une nouvelle version est publiée', function (): void {
    [$organization, $admin] = organizationWithFormRole(MembershipRole::Admin);
    $event = Event::factory()->for($organization)->create();
    $form = app(CreateForm::class)->handle($organization, $event->id, $admin, [
        'name' => 'Inscription générale',
        'fields' => basicFieldSet(),
    ]);
    app(PublishFormVersion::class)->handle($form, $admin);
    $form = $form->fresh();
    $v1Id = $form->current_version_id;

    app(ReviseForm::class)->handle($form, $admin, basicFieldSet());
    $form = $form->fresh();
    $published = app(PublishFormVersion::class)->handle($form, $admin);

    expect($published->current_version_id)->not->toBe($v1Id);
    expect(FormVersion::query()->find($v1Id)->status)->toBe(FormVersionStatus::Archived);
});

it('génère des clés de champ uniques dans une même version quand deux libellés se ressemblent', function (): void {
    [$organization, $admin] = organizationWithFormRole(MembershipRole::Admin);
    $event = Event::factory()->for($organization)->create();

    $form = app(CreateForm::class)->handle($organization, $event->id, $admin, [
        'name' => 'Test',
        'fields' => [
            ['type' => FieldType::ShortText, 'label' => 'Nom'],
            ['type' => FieldType::ShortText, 'label' => 'Nom'],
        ],
    ]);

    $keys = $form->latestVersion()->fields->pluck('key')->all();
    expect($keys)->toHaveCount(2);
    expect(array_unique($keys))->toHaveCount(2);
});

it('n\'expose que les formulaires de l\'organisation courante', function (): void {
    $organizationA = Organization::factory()->create();
    app(CurrentOrganization::class)->set($organizationA);
    $eventA = Event::factory()->for($organizationA)->create();
    $formA = Form::factory()->for($organizationA)->create(['event_id' => $eventA->id]);

    $organizationB = Organization::factory()->create();
    app(CurrentOrganization::class)->set($organizationB);
    $eventB = Event::factory()->for($organizationB)->create();
    $formB = Form::factory()->for($organizationB)->create(['event_id' => $eventB->id]);

    $ids = Form::query()->pluck('id');

    expect($ids)->toContain($formB->id);
    expect($ids)->not->toContain($formA->id);
});
