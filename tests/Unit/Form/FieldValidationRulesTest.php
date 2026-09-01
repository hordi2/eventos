<?php

declare(strict_types=1);

use App\Domain\Form\Models\FieldType;
use App\Domain\Form\Support\FieldValidationRules;
use Illuminate\Support\Facades\Validator;

it('marque un champ obligatoire comme "required", optionnel comme "nullable"', function (): void {
    $rules = app(FieldValidationRules::class);

    $required = makeField(FieldType::ShortText, ['is_required' => true]);
    $optional = makeField(FieldType::ShortText, ['is_required' => false]);

    expect($rules->forField($required)['test_field'])->toContain('required');
    expect($rules->forField($optional)['test_field'])->toContain('nullable');
});

it('rejette un texte court trop long', function (): void {
    $field = makeField(FieldType::ShortText, ['config' => ['max_length' => 5]]);
    $rules = app(FieldValidationRules::class)->forField($field);

    $validator = Validator::make(['test_field' => 'bien trop long'], $rules);

    expect($validator->fails())->toBeTrue();
});

it('accepte un nombre dans les bornes configurées', function (): void {
    $field = makeField(FieldType::Number, ['config' => ['min' => 1, 'max' => 10]]);
    $rules = app(FieldValidationRules::class)->forField($field);

    expect(Validator::make(['test_field' => 5], $rules)->fails())->toBeFalse();
    expect(Validator::make(['test_field' => 20], $rules)->fails())->toBeTrue();
});

it('valide la syntaxe d\'un e-mail', function (): void {
    $field = makeField(FieldType::Email);
    $rules = app(FieldValidationRules::class)->forField($field);

    expect(Validator::make(['test_field' => 'pas-un-email'], $rules)->fails())->toBeTrue();
});

it('n\'accepte qu\'une des valeurs d\'options pour un choix unique', function (): void {
    $field = makeField(FieldType::SingleChoice, [], [
        ['value' => 'poisson', 'label' => 'Poisson'],
        ['value' => 'vegetarien', 'label' => 'Végétarien'],
    ]);
    $rules = app(FieldValidationRules::class)->forField($field);

    expect(Validator::make(['test_field' => 'poisson'], $rules)->fails())->toBeFalse();
    expect(Validator::make(['test_field' => 'viande'], $rules)->fails())->toBeTrue();
});

it('valide chaque élément d\'un choix multiple contre la liste d\'options', function (): void {
    $field = makeField(FieldType::MultipleChoice, [], [
        ['value' => 'vegetarien', 'label' => 'Végétarien'],
        ['value' => 'sans_gluten', 'label' => 'Sans gluten'],
    ]);
    $rules = app(FieldValidationRules::class)->forField($field);

    expect(Validator::make(['test_field' => ['vegetarien']], $rules)->fails())->toBeFalse();
    expect(Validator::make(['test_field' => ['vegetarien', 'inconnu']], $rules)->fails())->toBeTrue();
});

it('exige que le consentement soit explicitement accepté', function (): void {
    $field = makeField(FieldType::Consent, ['is_required' => true]);
    $rules = app(FieldValidationRules::class)->forField($field);

    expect(Validator::make(['test_field' => false], $rules)->fails())->toBeTrue();
    expect(Validator::make(['test_field' => true], $rules)->fails())->toBeFalse();
});

it('interdit toute réponse sur un champ de texte informatif', function (): void {
    $field = makeField(FieldType::InformationalText);
    $rules = app(FieldValidationRules::class)->forField($field);

    expect(Validator::make(['test_field' => 'quelque chose'], $rules)->fails())->toBeTrue();
    expect(Validator::make([], $rules)->fails())->toBeFalse();
});
