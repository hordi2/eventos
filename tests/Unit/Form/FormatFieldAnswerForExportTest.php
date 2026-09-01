<?php

declare(strict_types=1);

use App\Domain\Form\Actions\FormatFieldAnswerForExport;
use App\Domain\Form\Models\FieldType;

it('affiche le libellé d\'une option plutôt que sa valeur technique', function (): void {
    $field = makeField(FieldType::SingleChoice, [], [
        ['value' => 'poisson', 'label' => 'Poisson'],
        ['value' => 'vegetarien', 'label' => 'Végétarien'],
    ]);

    expect(app(FormatFieldAnswerForExport::class)->handle($field, 'poisson'))->toBe('Poisson');
});

it('affiche les libellés d\'un choix multiple séparés par des virgules', function (): void {
    $field = makeField(FieldType::MultipleChoice, [], [
        ['value' => 'vegetarien', 'label' => 'Végétarien'],
        ['value' => 'sans_gluten', 'label' => 'Sans gluten'],
    ]);

    $export = app(FormatFieldAnswerForExport::class)->handle($field, ['vegetarien', 'sans_gluten']);

    expect($export)->toBe('Végétarien, Sans gluten');
});

it('affiche Oui/Non pour un champ oui/non', function (): void {
    $field = makeField(FieldType::YesNo);
    $format = app(FormatFieldAnswerForExport::class);

    expect($format->handle($field, true))->toBe('Oui');
    expect($format->handle($field, false))->toBe('Non');
});

it('affiche la date et l\'adresse IP d\'un consentement accepté', function (): void {
    $field = makeField(FieldType::Consent);

    $export = app(FormatFieldAnswerForExport::class)->handle($field, [
        'accepted' => true,
        'accepted_at' => '2026-09-02T14:30:00+00:00',
        'ip' => '203.0.113.5',
        'legal_text' => "J'accepte",
    ]);

    expect($export)->toBe('Accepté le 2026-09-02T14:30:00+00:00 depuis 203.0.113.5');
});

it('n\'exporte rien pour un texte informatif', function (): void {
    $field = makeField(FieldType::InformationalText);

    expect(app(FormatFieldAnswerForExport::class)->handle($field, null))->toBe('');
});
