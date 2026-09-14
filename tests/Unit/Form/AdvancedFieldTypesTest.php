<?php

declare(strict_types=1);

use App\Domain\Form\Actions\FormatFieldAnswerForExport;
use App\Domain\Form\Actions\NormalizeFieldAnswer;
use App\Domain\Form\Models\FieldType;
use App\Domain\Form\Support\FieldValidationRules;
use Illuminate\Support\Facades\Validator;

it('n\'accepte qu\'une option de la liste déroulante et exporte son libellé', function (): void {
    $field = makeField(FieldType::Dropdown, [], [
        ['value' => 'kinshasa', 'label' => 'Kinshasa'],
        ['value' => 'lubumbashi', 'label' => 'Lubumbashi'],
    ]);
    $rules = app(FieldValidationRules::class)->forField($field);

    expect(Validator::make(['test_field' => 'kinshasa'], $rules)->fails())->toBeFalse();
    expect(Validator::make(['test_field' => 'goma'], $rules)->fails())->toBeTrue();
    expect(app(FormatFieldAnswerForExport::class)->handle($field, 'lubumbashi'))->toBe('Lubumbashi');
});

it('garde une date et heure telle que saisie, sans conversion de fuseau', function (): void {
    $field = makeField(FieldType::DateTime);
    $rules = app(FieldValidationRules::class)->forField($field);

    expect(Validator::make(['test_field' => '2026-10-03T18:30'], $rules)->fails())->toBeFalse();
    expect(Validator::make(['test_field' => 'demain soir'], $rules)->fails())->toBeTrue();
    expect(app(NormalizeFieldAnswer::class)->handle($field, '2026-10-03T18:30'))->toBe('2026-10-03 18:30');
});

it('n\'accepte qu\'un lien web en http ou https', function (FieldType $type): void {
    $rules = app(FieldValidationRules::class)->forField(makeField($type));

    expect(Validator::make(['test_field' => 'https://www.linkedin.com/in/jean-kalala'], $rules)->fails())->toBeFalse();
    expect(Validator::make(['test_field' => 'javascript:alert(1)'], $rules)->fails())->toBeTrue();
    expect(Validator::make(['test_field' => 'pas un lien'], $rules)->fails())->toBeTrue();
})->with([FieldType::Url, FieldType::SocialProfile]);

it('borne une quantité entre le minimum et le maximum configurés', function (): void {
    $field = makeField(FieldType::Quantity, ['config' => ['min' => 1, 'max' => 4]]);
    $rules = app(FieldValidationRules::class)->forField($field);

    expect(Validator::make(['test_field' => 3], $rules)->fails())->toBeFalse();
    expect(Validator::make(['test_field' => 5], $rules)->fails())->toBeTrue();
    expect(Validator::make(['test_field' => 2.5], $rules)->fails())->toBeTrue();
    expect(app(NormalizeFieldAnswer::class)->handle($field, '3'))->toBe(3);
});

it('exige la rue et la ville d\'une adresse postale obligatoire', function (): void {
    $rules = app(FieldValidationRules::class)->forField(makeField(FieldType::PostalAddress, ['is_required' => true]));

    $complete = ['test_field' => ['line1' => '12 avenue du Commerce', 'city' => 'Kinshasa']];
    $withoutCity = ['test_field' => ['line1' => '12 avenue du Commerce']];

    expect(Validator::make($complete, $rules)->fails())->toBeFalse();
    expect(Validator::make($withoutCity, $rules)->errors()->has('test_field.city'))->toBeTrue();
});

it('ne garde que les parties renseignées d\'une adresse et l\'exporte sur une ligne', function (): void {
    $field = makeField(FieldType::PostalAddress);

    $normalized = app(NormalizeFieldAnswer::class)->handle($field, [
        'line1' => ' 12 avenue du Commerce ',
        'line2' => '',
        'city' => 'Kinshasa',
        'country' => 'RD Congo',
    ]);

    expect($normalized)->toBe(['line1' => '12 avenue du Commerce', 'city' => 'Kinshasa', 'country' => 'RD Congo']);
    expect(app(FormatFieldAnswerForExport::class)->handle($field, $normalized))->toBe('12 avenue du Commerce, Kinshasa, RD Congo');
});

it('exporte une adresse dans l\'ordre de lecture, même relue de la base dans un autre ordre', function (): void {
    $field = makeField(FieldType::PostalAddress);

    // Ordre des clés tel que PostgreSQL (jsonb) le rend.
    $fromDatabase = ['city' => 'Goma', 'line1' => '12 avenue du Commerce', 'country' => 'RD Congo'];

    expect(app(FormatFieldAnswerForExport::class)->handle($field, $fromDatabase))->toBe('12 avenue du Commerce, Goma, RD Congo');
});

it('réserve les questions avancées aux plans payants, jamais les types d\'origine', function (): void {
    expect(FieldType::Quantity->isPremium())->toBeTrue();
    expect(FieldType::PostalAddress->isPremium())->toBeTrue();
    expect(FieldType::Dropdown->isPremium())->toBeFalse();
    expect(FieldType::ShortText->isPremium())->toBeFalse();
});
