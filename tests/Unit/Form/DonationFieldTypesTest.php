<?php

declare(strict_types=1);

use App\Domain\Form\Actions\FormatFieldAnswerForExport;
use App\Domain\Form\Actions\NormalizeFieldAnswer;
use App\Domain\Form\Models\FieldType;
use App\Domain\Form\Models\FormField;
use App\Domain\Form\Support\FieldValidationRules;
use App\Support\Money;
use Illuminate\Support\Facades\Validator;

/**
 * @param  array<string, mixed>  $config
 */
function unitDonationField(array $config = [], bool $required = false): FormField
{
    return makeField(FieldType::Donation, [
        'is_required' => $required,
        'config' => ['currency' => 'XAF', 'amounts' => [10000, 5000], 'allow_custom' => true, ...$config],
    ]);
}

/**
 * @param  array<string, mixed>  $answer
 */
function donationAnswerPasses(FormField $field, array $answer): bool
{
    return Validator::make(['test_field' => $answer], app(FieldValidationRules::class)->forField($field))->passes();
}

it('accepte un montant proposé ou un montant libre lisible, et refuse le reste', function (): void {
    $field = unitDonationField();

    expect(donationAnswerPasses($field, ['choice' => '5000']))->toBeTrue();
    expect(donationAnswerPasses($field, ['choice' => 'autre', 'custom' => '12 500']))->toBeTrue();
    expect(donationAnswerPasses($field, []))->toBeTrue();

    expect(donationAnswerPasses($field, ['choice' => '7000']))->toBeFalse();
    expect(donationAnswerPasses($field, ['choice' => 'autre', 'custom' => '12,5']))->toBeFalse();
    expect(donationAnswerPasses($field, ['choice' => 'autre', 'custom' => '0']))->toBeFalse();
    expect(donationAnswerPasses($field, ['choice' => 'autre']))->toBeFalse();
});

it('refuse le montant libre quand l\'organisateur ne le propose pas, et exige un choix pour un don obligatoire', function (): void {
    expect(donationAnswerPasses(unitDonationField(['allow_custom' => false]), ['choice' => 'autre', 'custom' => '500']))->toBeFalse();
    expect(donationAnswerPasses(unitDonationField(required: true), ['custom' => null]))->toBeFalse();
});

it('enregistre le don en unité mineure et l\'affiche formaté dans l\'export', function (): void {
    $field = unitDonationField();

    $normalized = app(NormalizeFieldAnswer::class)->handle($field, ['choice' => 'autre', 'custom' => '12 500']);

    expect($normalized)->toBe(['amount_minor' => 12500, 'currency' => 'XAF']);
    expect(app(NormalizeFieldAnswer::class)->handle($field, ['choice' => null]))->toBe([]);
    expect(app(FormatFieldAnswerForExport::class)->handle($field, $normalized))->toBe(Money::fromMinorUnits(12500, 'XAF')->format());
});

it('regroupe l\'adresse du donateur et signale son souhait d\'anonymat dans l\'export', function (): void {
    $field = makeField(FieldType::DonorInfo);

    $normalized = app(NormalizeFieldAnswer::class)->handle($field, [
        'name' => ' Marie Lusala ',
        'company' => '',
        'line1' => '12 avenue du Port',
        'city' => 'Pointe-Noire',
        'anonymous' => '1',
    ]);

    expect($normalized)->toBe(['name' => 'Marie Lusala', 'address' => ['line1' => '12 avenue du Port', 'city' => 'Pointe-Noire'], 'anonymous' => true]);
    expect(app(FormatFieldAnswerForExport::class)->handle($field, $normalized))
        ->toBe('Marie Lusala — 12 avenue du Port, Pointe-Noire (souhaite rester anonyme)');
    expect(FieldType::Donation->isLockedAfterSubmission())->toBeTrue();
});
