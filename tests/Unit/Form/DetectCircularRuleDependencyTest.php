<?php

declare(strict_types=1);

use App\Domain\Form\Support\DetectCircularRuleDependency;

function groupReferencing(string ...$fieldKeys): array
{
    return [
        'combinator' => 'and',
        'conditions' => array_map(fn (string $key): array => ['field_key' => $key, 'operator' => 'is_not_empty'], $fieldKeys),
    ];
}

it('ne détecte aucune boucle dans une chaîne de dépendances linéaire', function (): void {
    $detector = app(DetectCircularRuleDependency::class);

    $graph = [
        'z' => groupReferencing('y'),
        'y' => groupReferencing('x'),
        'x' => groupReferencing('w'),
    ];

    expect($detector->hasCycle($graph))->toBeFalse();
});

it('détecte une boucle directe entre deux champs', function (): void {
    $detector = app(DetectCircularRuleDependency::class);

    $graph = [
        'a' => groupReferencing('b'),
        'b' => groupReferencing('a'),
    ];

    expect($detector->hasCycle($graph))->toBeTrue();
});

it('détecte une boucle transitive à travers plusieurs champs', function (): void {
    $detector = app(DetectCircularRuleDependency::class);

    $graph = [
        'a' => groupReferencing('b'),
        'b' => groupReferencing('c'),
        'c' => groupReferencing('a'),
    ];

    expect($detector->hasCycle($graph))->toBeTrue();
});

it('détecte un champ qui dépend de lui-même', function (): void {
    $detector = app(DetectCircularRuleDependency::class);

    $graph = ['a' => groupReferencing('a')];

    expect($detector->hasCycle($graph))->toBeTrue();
});

it('ne signale pas de boucle pour des branches indépendantes qui partagent une source', function (): void {
    $detector = app(DetectCircularRuleDependency::class);

    $graph = [
        'z' => groupReferencing('x'),
        'y' => groupReferencing('x'),
    ];

    expect($detector->hasCycle($graph))->toBeFalse();
});
