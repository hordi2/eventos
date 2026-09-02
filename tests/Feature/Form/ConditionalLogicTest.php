<?php

declare(strict_types=1);

use App\Domain\Event\Models\Event;
use App\Domain\Form\Actions\CreateForm;
use App\Domain\Form\InvalidConditionalRuleException;
use App\Domain\Form\Models\ConditionalRule;
use App\Domain\Form\Models\FieldType;
use App\Domain\Form\Support\BuildFormValidationRules;
use App\Domain\Form\Support\EvaluateFormVisibility;
use App\Domain\Organization\Models\MembershipRole;
use App\Domain\Organization\Models\Organization;
use App\Models\User;
use App\Support\MultiTenancy\CurrentOrganization;
use Illuminate\Support\Facades\Validator;

function organizationWithConditionalLogicRole(MembershipRole $role): array
{
    $organization = Organization::factory()->create();
    app(CurrentOrganization::class)->set($organization);

    $user = User::factory()->create();
    $user->memberships()->create(['organization_id' => $organization->id, 'role' => $role]);

    return [$organization, $user];
}

it('masque un champ par défaut jusqu\'à ce que la condition "afficher" soit remplie', function (): void {
    [$organization, $admin] = organizationWithConditionalLogicRole(MembershipRole::Admin);
    $event = Event::factory()->for($organization)->create();

    $form = app(CreateForm::class)->handle($organization, $event->id, $admin, [
        'name' => 'Inscription',
        'fields' => [
            ['key' => 'diner', 'type' => FieldType::YesNo, 'label' => 'Participez-vous au dîner ?'],
            ['key' => 'menu', 'type' => FieldType::ShortText, 'label' => 'Choix du menu'],
        ],
        'rules' => [
            [
                'target_field_key' => 'menu',
                'action' => 'show',
                'condition_group' => ['combinator' => 'and', 'conditions' => [
                    ['field_key' => 'diner', 'operator' => 'is', 'value' => true],
                ]],
            ],
        ],
    ]);

    $version = $form->latestVersion();
    $evaluate = app(EvaluateFormVisibility::class);

    expect($evaluate->handle($version, ['diner' => false])['menu']['visible'])->toBeFalse();
    expect($evaluate->handle($version, ['diner' => true])['menu']['visible'])->toBeTrue();
    expect($evaluate->handle($version, [])['menu']['visible'])->toBeFalse();
});

it('affiche un champ par défaut jusqu\'à ce que la condition "masquer" soit remplie', function (): void {
    [$organization, $admin] = organizationWithConditionalLogicRole(MembershipRole::Admin);
    $event = Event::factory()->for($organization)->create();

    $form = app(CreateForm::class)->handle($organization, $event->id, $admin, [
        'name' => 'Inscription',
        'fields' => [
            ['key' => 'statut', 'type' => FieldType::ShortText, 'label' => 'Statut'],
            ['key' => 'raison', 'type' => FieldType::ShortText, 'label' => 'Raison'],
        ],
        'rules' => [
            [
                'target_field_key' => 'raison',
                'action' => 'hide',
                'condition_group' => ['combinator' => 'and', 'conditions' => [
                    ['field_key' => 'statut', 'operator' => 'is', 'value' => 'confirme'],
                ]],
            ],
        ],
    ]);

    $version = $form->latestVersion();
    $evaluate = app(EvaluateFormVisibility::class);

    expect($evaluate->handle($version, ['statut' => 'confirme'])['raison']['visible'])->toBeFalse();
    expect($evaluate->handle($version, ['statut' => 'decline'])['raison']['visible'])->toBeTrue();
});

it('rend un champ obligatoire seulement quand la condition est remplie', function (): void {
    [$organization, $admin] = organizationWithConditionalLogicRole(MembershipRole::Admin);
    $event = Event::factory()->for($organization)->create();

    $form = app(CreateForm::class)->handle($organization, $event->id, $admin, [
        'name' => 'Inscription',
        'fields' => [
            ['key' => 'accompagnant', 'type' => FieldType::YesNo, 'label' => 'Venez-vous accompagné ?'],
            ['key' => 'nom_accompagnant', 'type' => FieldType::ShortText, 'label' => 'Nom de l\'accompagnant', 'is_required' => false],
        ],
        'rules' => [
            [
                'target_field_key' => 'nom_accompagnant',
                'action' => 'require',
                'condition_group' => ['combinator' => 'and', 'conditions' => [
                    ['field_key' => 'accompagnant', 'operator' => 'is', 'value' => true],
                ]],
            ],
        ],
    ]);

    $version = $form->latestVersion();
    $evaluate = app(EvaluateFormVisibility::class);

    expect($evaluate->handle($version, ['accompagnant' => true])['nom_accompagnant']['required'])->toBeTrue();
    expect($evaluate->handle($version, ['accompagnant' => false])['nom_accompagnant']['required'])->toBeFalse();
});

it('interdit toute valeur soumise pour un champ masqué par la logique conditionnelle', function (): void {
    [$organization, $admin] = organizationWithConditionalLogicRole(MembershipRole::Admin);
    $event = Event::factory()->for($organization)->create();

    $form = app(CreateForm::class)->handle($organization, $event->id, $admin, [
        'name' => 'Inscription',
        'fields' => [
            ['key' => 'diner', 'type' => FieldType::ShortText, 'label' => 'Dîner ?'],
            ['key' => 'menu', 'type' => FieldType::ShortText, 'label' => 'Menu'],
        ],
        'rules' => [
            [
                'target_field_key' => 'menu',
                'action' => 'show',
                'condition_group' => ['combinator' => 'and', 'conditions' => [
                    ['field_key' => 'diner', 'operator' => 'is', 'value' => 'oui'],
                ]],
            ],
        ],
    ]);

    $rules = app(BuildFormValidationRules::class)->handle($form->latestVersion(), ['diner' => 'non']);

    expect($rules['menu'])->toBe(['prohibited']);
    expect(Validator::make(['diner' => 'non', 'menu' => 'poisson'], $rules)->fails())->toBeTrue();
    expect(Validator::make(['diner' => 'non'], $rules)->fails())->toBeFalse();
});

it('refuse deux règles ciblant le même champ', function (): void {
    [$organization, $admin] = organizationWithConditionalLogicRole(MembershipRole::Admin);
    $event = Event::factory()->for($organization)->create();

    app(CreateForm::class)->handle($organization, $event->id, $admin, [
        'name' => 'Inscription',
        'fields' => [
            ['key' => 'a', 'type' => FieldType::ShortText, 'label' => 'A'],
            ['key' => 'b', 'type' => FieldType::ShortText, 'label' => 'B'],
        ],
        'rules' => [
            ['target_field_key' => 'b', 'action' => 'show', 'condition_group' => ['combinator' => 'and', 'conditions' => [['field_key' => 'a', 'operator' => 'is_not_empty']]]],
            ['target_field_key' => 'b', 'action' => 'hide', 'condition_group' => ['combinator' => 'and', 'conditions' => [['field_key' => 'a', 'operator' => 'is_empty']]]],
        ],
    ]);
})->throws(InvalidConditionalRuleException::class);

it('refuse une règle qui référence un champ inexistant', function (): void {
    [$organization, $admin] = organizationWithConditionalLogicRole(MembershipRole::Admin);
    $event = Event::factory()->for($organization)->create();

    app(CreateForm::class)->handle($organization, $event->id, $admin, [
        'name' => 'Inscription',
        'fields' => [
            ['key' => 'a', 'type' => FieldType::ShortText, 'label' => 'A'],
        ],
        'rules' => [
            ['target_field_key' => 'inconnu', 'action' => 'show', 'condition_group' => ['combinator' => 'and', 'conditions' => [['field_key' => 'a', 'operator' => 'is_not_empty']]]],
        ],
    ]);
})->throws(InvalidConditionalRuleException::class);

it('refuse une boucle directe au moment de la sauvegarde', function (): void {
    [$organization, $admin] = organizationWithConditionalLogicRole(MembershipRole::Admin);
    $event = Event::factory()->for($organization)->create();

    app(CreateForm::class)->handle($organization, $event->id, $admin, [
        'name' => 'Inscription',
        'fields' => [
            ['key' => 'a', 'type' => FieldType::ShortText, 'label' => 'A'],
            ['key' => 'b', 'type' => FieldType::ShortText, 'label' => 'B'],
        ],
        'rules' => [
            ['target_field_key' => 'a', 'action' => 'show', 'condition_group' => ['combinator' => 'and', 'conditions' => [['field_key' => 'b', 'operator' => 'is_not_empty']]]],
            ['target_field_key' => 'b', 'action' => 'show', 'condition_group' => ['combinator' => 'and', 'conditions' => [['field_key' => 'a', 'operator' => 'is_not_empty']]]],
        ],
    ]);
})->throws(InvalidConditionalRuleException::class);

it('résout correctement une chaîne de dépendances à 5 niveaux', function (): void {
    [$organization, $admin] = organizationWithConditionalLogicRole(MembershipRole::Admin);
    $event = Event::factory()->for($organization)->create();

    // f5 dépend de f4, qui dépend de f3, qui dépend de f2, qui dépend de f1
    // (5 niveaux de champs cibles imbriqués, chacun contrôlé par le précédent).
    $form = app(CreateForm::class)->handle($organization, $event->id, $admin, [
        'name' => 'Inscription',
        'fields' => [
            ['key' => 'f1', 'type' => FieldType::YesNo, 'label' => 'Niveau 1'],
            ['key' => 'f2', 'type' => FieldType::YesNo, 'label' => 'Niveau 2'],
            ['key' => 'f3', 'type' => FieldType::YesNo, 'label' => 'Niveau 3'],
            ['key' => 'f4', 'type' => FieldType::YesNo, 'label' => 'Niveau 4'],
            ['key' => 'f5', 'type' => FieldType::ShortText, 'label' => 'Niveau 5'],
        ],
        'rules' => [
            ['target_field_key' => 'f2', 'action' => 'show', 'condition_group' => ['combinator' => 'and', 'conditions' => [['field_key' => 'f1', 'operator' => 'is', 'value' => true]]]],
            ['target_field_key' => 'f3', 'action' => 'show', 'condition_group' => ['combinator' => 'and', 'conditions' => [['field_key' => 'f2', 'operator' => 'is', 'value' => true]]]],
            ['target_field_key' => 'f4', 'action' => 'show', 'condition_group' => ['combinator' => 'and', 'conditions' => [['field_key' => 'f3', 'operator' => 'is', 'value' => true]]]],
            ['target_field_key' => 'f5', 'action' => 'show', 'condition_group' => ['combinator' => 'and', 'conditions' => [['field_key' => 'f4', 'operator' => 'is', 'value' => true]]]],
        ],
    ]);

    $version = $form->latestVersion();
    $evaluate = app(EvaluateFormVisibility::class);

    // Toute la chaîne répond "oui" : f5 visible tout en bas.
    $allYes = ['f1' => true, 'f2' => true, 'f3' => true, 'f4' => true];
    expect($evaluate->handle($version, $allYes)['f5']['visible'])->toBeTrue();

    // Le maillon du milieu (f3) répond "non" : f4 et f5 restent masqués,
    // même si f4 lui-même n'a jamais été répondu.
    $brokenChain = ['f1' => true, 'f2' => true, 'f3' => false];
    expect($evaluate->handle($version, $brokenChain)['f4']['visible'])->toBeFalse();
    expect($evaluate->handle($version, $brokenChain)['f5']['visible'])->toBeFalse();
});

function formWithOneRule(Organization $organization, User $admin): ConditionalRule
{
    $event = Event::factory()->for($organization)->create();

    $form = app(CreateForm::class)->handle($organization, $event->id, $admin, [
        'name' => 'Inscription',
        'fields' => [
            ['key' => 'a', 'type' => FieldType::ShortText, 'label' => 'A'],
            ['key' => 'b', 'type' => FieldType::ShortText, 'label' => 'B'],
        ],
        'rules' => [
            ['target_field_key' => 'b', 'action' => 'show', 'condition_group' => ['combinator' => 'and', 'conditions' => [['field_key' => 'a', 'operator' => 'is_not_empty']]]],
        ],
    ]);

    return $form->latestVersion()->conditionalRules->sole();
}

it('n\'expose que les règles de l\'organisation courante', function (): void {
    [$organizationA, $adminA] = organizationWithConditionalLogicRole(MembershipRole::Admin);
    $ruleA = formWithOneRule($organizationA, $adminA);

    [$organizationB, $adminB] = organizationWithConditionalLogicRole(MembershipRole::Admin);
    $ruleB = formWithOneRule($organizationB, $adminB);

    $ids = ConditionalRule::query()->pluck('id');

    expect($ids)->toContain($ruleB->id);
    expect($ids)->not->toContain($ruleA->id);
});
