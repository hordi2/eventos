<?php

declare(strict_types=1);

use App\Domain\Form\Actions\NormalizeFieldAnswer;
use App\Domain\Form\InvalidFieldAnswerException;
use App\Domain\Form\Models\FieldType;

it('coupe les espaces superflus d\'un texte', function (): void {
    $field = makeField(FieldType::ShortText);

    expect(app(NormalizeFieldAnswer::class)->handle($field, '  Jean Dupont  '))->toBe('Jean Dupont');
});

it('convertit un nombre entier et un nombre décimal correctement', function (): void {
    $field = makeField(FieldType::Number);

    expect(app(NormalizeFieldAnswer::class)->handle($field, '42'))->toBe(42);
    expect(app(NormalizeFieldAnswer::class)->handle($field, '4.5'))->toBe(4.5);
});

it('met un e-mail en minuscules sans espaces', function (): void {
    $field = makeField(FieldType::Email);

    expect(app(NormalizeFieldAnswer::class)->handle($field, '  Jean.Dupont@Example.COM '))->toBe('jean.dupont@example.com');
});

it('normalise un numéro de téléphone local en E.164 selon l\'indicatif configuré', function (): void {
    $field = makeField(FieldType::Phone, ['config' => ['default_country' => 'CD']]);

    expect(app(NormalizeFieldAnswer::class)->handle($field, '0812345678'))->toBe('+243812345678');
});

it('normalise un numéro déjà international sans avoir besoin de l\'indicatif par défaut', function (): void {
    $field = makeField(FieldType::Phone);

    expect(app(NormalizeFieldAnswer::class)->handle($field, '+33612345678'))->toBe('+33612345678');
});

it('rejette un numéro de téléphone qui n\'est pas valide pour son pays', function (): void {
    $field = makeField(FieldType::Phone, ['config' => ['default_country' => 'CD']]);

    app(NormalizeFieldAnswer::class)->handle($field, '123');
})->throws(InvalidFieldAnswerException::class);

it('normalise une date en chaîne ISO 8601', function (): void {
    $field = makeField(FieldType::Date);

    expect(app(NormalizeFieldAnswer::class)->handle($field, '2026-09-08'))->toContain('2026-09-08');
});

it('normalise un choix multiple en tableau de chaînes', function (): void {
    $field = makeField(FieldType::MultipleChoice);

    expect(app(NormalizeFieldAnswer::class)->handle($field, ['vegetarien', 'sans_gluten']))
        ->toBe(['vegetarien', 'sans_gluten']);
});

it('normalise plusieurs représentations de oui/non en booléen', function (): void {
    $field = makeField(FieldType::YesNo);
    $normalize = app(NormalizeFieldAnswer::class);

    expect($normalize->handle($field, true))->toBeTrue();
    expect($normalize->handle($field, '1'))->toBeTrue();
    expect($normalize->handle($field, false))->toBeFalse();
    expect($normalize->handle($field, '0'))->toBeFalse();
});

it('horodate le consentement et enregistre l\'adresse IP', function (): void {
    $field = makeField(FieldType::Consent, ['config' => ['legal_text' => "J'accepte les conditions"]]);

    $answer = app(NormalizeFieldAnswer::class)->handle($field, true, '203.0.113.5');

    expect($answer['accepted'])->toBeTrue();
    expect($answer['ip'])->toBe('203.0.113.5');
    expect($answer['legal_text'])->toBe("J'accepte les conditions");
    expect($answer['accepted_at'])->not->toBeNull();
});

it('refuse un consentement non coché', function (): void {
    $field = makeField(FieldType::Consent);

    app(NormalizeFieldAnswer::class)->handle($field, false, '203.0.113.5');
})->throws(InvalidFieldAnswerException::class);

it('ne produit aucune valeur pour un champ de texte informatif', function (): void {
    $field = makeField(FieldType::InformationalText);

    expect(app(NormalizeFieldAnswer::class)->handle($field, 'quoi que ce soit'))->toBeNull();
});
