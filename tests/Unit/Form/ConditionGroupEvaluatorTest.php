<?php

declare(strict_types=1);

use App\Domain\Form\Support\ConditionGroupEvaluator;

function condition(string $field, string $operator, mixed $value = null): array
{
    return ['field_key' => $field, 'operator' => $operator, 'value' => $value];
}

it('évalue l\'opérateur "est"', function (): void {
    $evaluator = app(ConditionGroupEvaluator::class);
    $group = ['combinator' => 'and', 'conditions' => [condition('diner', 'is', 'oui')]];

    expect($evaluator->evaluate($group, ['diner' => 'oui']))->toBeTrue();
    expect($evaluator->evaluate($group, ['diner' => 'non']))->toBeFalse();
});

it('évalue l\'opérateur "n\'est pas"', function (): void {
    $evaluator = app(ConditionGroupEvaluator::class);
    $group = ['combinator' => 'and', 'conditions' => [condition('diner', 'is_not', 'oui')]];

    expect($evaluator->evaluate($group, ['diner' => 'non']))->toBeTrue();
    expect($evaluator->evaluate($group, ['diner' => 'oui']))->toBeFalse();
});

it('évalue "contient" sur une chaîne et sur un tableau de choix multiple', function (): void {
    $evaluator = app(ConditionGroupEvaluator::class);
    $group = ['combinator' => 'and', 'conditions' => [condition('allergies', 'contains', 'gluten')]];

    expect($evaluator->evaluate($group, ['allergies' => 'sans gluten']))->toBeTrue();
    expect($evaluator->evaluate($group, ['allergies' => ['vegetarien', 'gluten']]))->toBeTrue();
    expect($evaluator->evaluate($group, ['allergies' => ['vegetarien']]))->toBeFalse();
});

it('évalue "ne contient pas"', function (): void {
    $evaluator = app(ConditionGroupEvaluator::class);
    $group = ['combinator' => 'and', 'conditions' => [condition('allergies', 'does_not_contain', 'gluten')]];

    expect($evaluator->evaluate($group, ['allergies' => 'sans gluten']))->toBeFalse();
    expect($evaluator->evaluate($group, ['allergies' => 'vegetarien']))->toBeTrue();
});

it('évalue supérieur à et inférieur à', function (): void {
    $evaluator = app(ConditionGroupEvaluator::class);
    $greater = ['combinator' => 'and', 'conditions' => [condition('age', 'greater_than', 18)]];
    $less = ['combinator' => 'and', 'conditions' => [condition('age', 'less_than', 18)]];

    expect($evaluator->evaluate($greater, ['age' => 25]))->toBeTrue();
    expect($evaluator->evaluate($greater, ['age' => 10]))->toBeFalse();
    expect($evaluator->evaluate($less, ['age' => 10]))->toBeTrue();
});

it('évalue est vide et n\'est pas vide', function (): void {
    $evaluator = app(ConditionGroupEvaluator::class);
    $empty = ['combinator' => 'and', 'conditions' => [condition('commentaire', 'is_empty')]];
    $notEmpty = ['combinator' => 'and', 'conditions' => [condition('commentaire', 'is_not_empty')]];

    expect($evaluator->evaluate($empty, ['commentaire' => '']))->toBeTrue();
    expect($evaluator->evaluate($empty, []))->toBeTrue();
    expect($evaluator->evaluate($empty, ['commentaire' => 'quelque chose']))->toBeFalse();
    expect($evaluator->evaluate($notEmpty, ['commentaire' => 'quelque chose']))->toBeTrue();
});

it('combine plusieurs conditions avec ET', function (): void {
    $evaluator = app(ConditionGroupEvaluator::class);
    $group = [
        'combinator' => 'and',
        'conditions' => [condition('diner', 'is', 'oui'), condition('age', 'greater_than', 18)],
    ];

    expect($evaluator->evaluate($group, ['diner' => 'oui', 'age' => 25]))->toBeTrue();
    expect($evaluator->evaluate($group, ['diner' => 'oui', 'age' => 10]))->toBeFalse();
});

it('combine plusieurs conditions avec OU', function (): void {
    $evaluator = app(ConditionGroupEvaluator::class);
    $group = [
        'combinator' => 'or',
        'conditions' => [condition('diner', 'is', 'oui'), condition('vip', 'is', 'oui')],
    ];

    expect($evaluator->evaluate($group, ['diner' => 'non', 'vip' => 'oui']))->toBeTrue();
    expect($evaluator->evaluate($group, ['diner' => 'non', 'vip' => 'non']))->toBeFalse();
});

it('évalue des groupes de conditions imbriqués', function (): void {
    $evaluator = app(ConditionGroupEvaluator::class);
    $group = [
        'combinator' => 'and',
        'conditions' => [
            condition('diner', 'is', 'oui'),
            [
                'combinator' => 'or',
                'conditions' => [condition('vip', 'is', 'oui'), condition('age', 'greater_than', 65)],
            ],
        ],
    ];

    expect($evaluator->evaluate($group, ['diner' => 'oui', 'vip' => 'oui', 'age' => 20]))->toBeTrue();
    expect($evaluator->evaluate($group, ['diner' => 'oui', 'vip' => 'non', 'age' => 70]))->toBeTrue();
    expect($evaluator->evaluate($group, ['diner' => 'oui', 'vip' => 'non', 'age' => 20]))->toBeFalse();
    expect($evaluator->evaluate($group, ['diner' => 'non', 'vip' => 'oui', 'age' => 70]))->toBeFalse();
});

it('traite un champ absent des réponses comme vide', function (): void {
    $evaluator = app(ConditionGroupEvaluator::class);
    $group = ['combinator' => 'and', 'conditions' => [condition('jamais_repondu', 'is_empty')]];

    expect($evaluator->evaluate($group, []))->toBeTrue();
});
