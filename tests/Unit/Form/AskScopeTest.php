<?php

declare(strict_types=1);

use App\Domain\Form\Models\FieldType;
use App\Domain\Form\Models\FormVersion;
use App\Domain\Form\Support\AskScope;
use App\Domain\Form\Support\OptionReservationKey;

it('donne à un accompagnant les réponses communes du titulaire et seulement les siennes aux questions par personne', function (): void {
    $version = new FormVersion;
    $version->setRelation('fields', collect([
        makeField(FieldType::ShortText, ['key' => 'societe']),
        makeField(FieldType::MealChoice, ['key' => 'menu', 'config' => ['ask_scope' => 'each_attendee']]),
        makeField(FieldType::ShortText, ['key' => 'allergies', 'config' => ['ask_scope' => 'each_attendee']]),
    ]));

    $answers = AskScope::answersFor($version, ['societe' => 'Itaza', 'menu' => 'poisson', 'allergies' => 'arachides'], ['menu' => 'poulet']);

    expect($answers)->toBe(['societe' => 'Itaza', 'menu' => 'poulet']);
});

it('donne une clé de quota distincte à chaque accompagnant sans changer celle du titulaire', function (): void {
    expect(OptionReservationKey::for('draft:12', 7))->toBe('draft:12:option:7');
    expect(OptionReservationKey::for('draft:12', 7, 45))->toBe('draft:12:attendee:45:option:7');
});
