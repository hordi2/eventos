<?php

declare(strict_types=1);

use App\Domain\CheckIn\Actions\AutoPlaceGuests;

function guest(string $type, int $id): array
{
    return ['guest_type' => $type, 'guest_id' => $id];
}

it('place chaque invité sur une table avec assez de place, sans dépasser la capacité', function (): void {
    $guests = [guest('attendee', 1), guest('attendee', 2), guest('attendee', 3)];
    $tables = [['id' => 10, 'remaining' => 2], ['id' => 20, 'remaining' => 2]];

    $result = app(AutoPlaceGuests::class)->handle($guests, $tables, []);

    expect($result->unplaced)->toBeEmpty();
    expect($result->assignments)->toHaveCount(3);

    $perTable = collect($result->assignments)->countBy('seating_table_id');
    expect($perTable->get(10, 0))->toBeLessThanOrEqual(2);
    expect($perTable->get(20, 0))->toBeLessThanOrEqual(2);
});

it('place ensemble deux invités liés par une contrainte "doit être avec"', function (): void {
    $guests = [guest('attendee', 1), guest('attendee', 2), guest('attendee', 3)];
    $tables = [['id' => 10, 'remaining' => 2], ['id' => 20, 'remaining' => 2]];
    $constraints = [['guest_a' => 'attendee:1', 'guest_b' => 'attendee:2', 'type' => 'must_be_with']];

    $result = app(AutoPlaceGuests::class)->handle($guests, $tables, $constraints);

    $tableOf = collect($result->assignments)->keyBy(fn (array $a) => "{$a['guest_type']}:{$a['guest_id']}")
        ->map(fn (array $a) => $a['seating_table_id']);

    expect($tableOf['attendee:1'])->toBe($tableOf['attendee:2']);
});

it('ne place jamais ensemble deux invités contraints "ne doit pas être avec"', function (): void {
    $guests = [guest('attendee', 1), guest('attendee', 2)];
    // Une seule table avec assez de place pour les deux : sans la
    // contrainte, ils y seraient placés ensemble.
    $tables = [['id' => 10, 'remaining' => 2]];
    $constraints = [['guest_a' => 'attendee:1', 'guest_b' => 'attendee:2', 'type' => 'must_not_be_with']];

    $result = app(AutoPlaceGuests::class)->handle($guests, $tables, $constraints);

    // Un des deux invités ne peut pas être placé : la seule table
    // disponible est déjà occupée par l'autre.
    expect($result->unplaced)->toHaveCount(1);
    expect($result->assignments)->toHaveCount(1);
});

it('renvoie les invités non placés quand aucune table ne suffit', function (): void {
    $guests = [guest('attendee', 1), guest('attendee', 2)];
    $tables = [['id' => 10, 'remaining' => 1]];

    $result = app(AutoPlaceGuests::class)->handle($guests, $tables, []);

    expect($result->assignments)->toHaveCount(1);
    expect($result->unplaced)->toHaveCount(1);
});

it('équilibre le remplissage en tassant la table qui laisse le moins de place libre', function (): void {
    $guests = [guest('attendee', 1)];
    $tables = [['id' => 10, 'remaining' => 5], ['id' => 20, 'remaining' => 1]];

    $result = app(AutoPlaceGuests::class)->handle($guests, $tables, []);

    expect($result->assignments[0]['seating_table_id'])->toBe(20);
});

function accessibleGuest(string $type, int $id): array
{
    return ['guest_type' => $type, 'guest_id' => $id, 'needs_accessible' => true];
}

it('place un invité à mobilité réduite uniquement sur une table accessible', function (): void {
    $guests = [accessibleGuest('attendee', 1)];
    // La table non accessible laisserait pourtant moins de place vide :
    // sans la règle d'accessibilité, l'heuristique de tassement l'aurait choisie.
    $tables = [
        ['id' => 10, 'remaining' => 2, 'is_accessible' => false],
        ['id' => 20, 'remaining' => 5, 'is_accessible' => true],
    ];

    $result = app(AutoPlaceGuests::class)->handle($guests, $tables, []);

    expect($result->assignments)->toHaveCount(1);
    expect($result->assignments[0]['seating_table_id'])->toBe(20);
});

it('installe l\'accompagnant lié par « doit être avec » à la même table accessible', function (): void {
    $guests = [accessibleGuest('attendee', 1), guest('attendee', 2)];
    $tables = [
        ['id' => 10, 'remaining' => 5, 'is_accessible' => false],
        ['id' => 20, 'remaining' => 2, 'is_accessible' => true],
    ];
    $constraints = [['guest_a' => 'attendee:1', 'guest_b' => 'attendee:2', 'type' => 'must_be_with']];

    $result = app(AutoPlaceGuests::class)->handle($guests, $tables, $constraints);

    $tableOf = collect($result->assignments)->keyBy(fn (array $a) => "{$a['guest_type']}:{$a['guest_id']}")
        ->map(fn (array $a) => $a['seating_table_id']);

    expect($tableOf['attendee:1'])->toBe(20);
    expect($tableOf['attendee:2'])->toBe(20);
});

it('laisse non placé un invité à mobilité réduite quand aucune table accessible n\'a de place', function (): void {
    $guests = [accessibleGuest('attendee', 1)];
    $tables = [['id' => 10, 'remaining' => 5, 'is_accessible' => false]];

    $result = app(AutoPlaceGuests::class)->handle($guests, $tables, []);

    expect($result->assignments)->toBeEmpty();
    expect($result->unplaced)->toHaveCount(1);
});

it('garde les tables accessibles libres en plaçant d\'abord les autres invités ailleurs', function (): void {
    $guests = [guest('attendee', 1)];
    // La table accessible laisserait moins de place vide (meilleur tassement),
    // mais un invité sans besoin particulier doit d'abord essayer ailleurs.
    $tables = [
        ['id' => 10, 'remaining' => 5, 'is_accessible' => false],
        ['id' => 20, 'remaining' => 1, 'is_accessible' => true],
    ];

    $result = app(AutoPlaceGuests::class)->handle($guests, $tables, []);

    expect($result->assignments[0]['seating_table_id'])->toBe(10);
});
