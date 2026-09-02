<?php

declare(strict_types=1);

namespace App\Domain\Form\Support;

use App\Domain\Form\Models\ConditionOperator;

/**
 * Évalue un arbre de conditions ET/OU contre un jeu de réponses. Seule
 * source de vérité pour l'évaluation d'une règle : le serveur (ici) et un
 * futur client JS (T-023/T-031) doivent lire exactement la même structure
 * condition_group plutôt que réimplémenter chacun leur propre logique — la
 * garantie « jamais de divergence » (critère du ticket) vient de ne
 * jamais dupliquer cette évaluation, pas de synchroniser deux moteurs.
 */
final class ConditionGroupEvaluator
{
    /**
     * @param  array<string, mixed>  $group
     * @param  array<string, mixed>  $answers
     */
    public function evaluate(array $group, array $answers): bool
    {
        $combinator = $group['combinator'] ?? 'and';
        $conditions = $group['conditions'] ?? [];

        if ($conditions === []) {
            return $combinator === 'and';
        }

        $results = array_map(
            fn (array $node): bool => isset($node['combinator'])
                ? $this->evaluate($node, $answers)
                : $this->evaluateCondition($node, $answers),
            $conditions,
        );

        return $combinator === 'or'
            ? in_array(true, $results, true)
            : ! in_array(false, $results, true);
    }

    /**
     * @param  array<string, mixed>  $condition
     * @param  array<string, mixed>  $answers
     */
    private function evaluateCondition(array $condition, array $answers): bool
    {
        $value = $answers[$condition['field_key']] ?? null;
        $target = $condition['value'] ?? null;
        $operator = ConditionOperator::from($condition['operator']);

        return match ($operator) {
            ConditionOperator::Is => $this->looseEquals($value, $target),
            ConditionOperator::IsNot => ! $this->looseEquals($value, $target),
            ConditionOperator::Contains => $this->contains($value, $target),
            ConditionOperator::DoesNotContain => ! $this->contains($value, $target),
            ConditionOperator::GreaterThan => is_numeric($value) && is_numeric($target) && (float) $value > (float) $target,
            ConditionOperator::LessThan => is_numeric($value) && is_numeric($target) && (float) $value < (float) $target,
            ConditionOperator::IsEmpty => $this->isEmpty($value),
            ConditionOperator::IsNotEmpty => ! $this->isEmpty($value),
        };
    }

    private function looseEquals(mixed $value, mixed $target): bool
    {
        if (is_bool($value) || is_bool($target)) {
            return filter_var($value, FILTER_VALIDATE_BOOLEAN) === filter_var($target, FILTER_VALIDATE_BOOLEAN);
        }

        return (string) $value === (string) $target;
    }

    private function contains(mixed $value, mixed $target): bool
    {
        if (is_array($value)) {
            return in_array((string) $target, array_map(strval(...), $value), true);
        }

        return str_contains((string) $value, (string) $target);
    }

    private function isEmpty(mixed $value): bool
    {
        if (is_array($value)) {
            return $value === [];
        }

        return $value === null || $value === '';
    }
}
