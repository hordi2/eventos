<?php

declare(strict_types=1);

namespace App\Domain\Form\Support;

/**
 * Détecte une référence circulaire entre règles au moment de la sauvegarde
 * (critère du ticket T-022) : la règle qui affiche/masque/rend obligatoire
 * le champ Z ne doit jamais, même transitivement, dépendre de la valeur
 * de Z lui-même.
 */
final class DetectCircularRuleDependency
{
    /**
     * Clé = champ ciblé par une règle, valeur = son condition_group.
     *
     * @param  array<string, array<string, mixed>>  $conditionGroupsByTargetKey
     */
    public function hasCycle(array $conditionGroupsByTargetKey): bool
    {
        $graph = array_map(
            fn (array $group): array => $this->extractFieldKeys($group),
            $conditionGroupsByTargetKey,
        );

        foreach (array_keys($graph) as $node) {
            if ($this->hasPathBackTo($graph, $node, $node, [])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, list<string>>  $graph
     * @param  list<string>  $visited
     */
    private function hasPathBackTo(array $graph, string $current, string $target, array $visited): bool
    {
        foreach ($graph[$current] ?? [] as $next) {
            if ($next === $target) {
                return true;
            }

            if (in_array($next, $visited, true)) {
                continue;
            }

            if ($this->hasPathBackTo($graph, $next, $target, [...$visited, $next])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $group
     * @return list<string>
     */
    private function extractFieldKeys(array $group): array
    {
        $keys = [];

        foreach ($group['conditions'] ?? [] as $node) {
            $keys = isset($node['combinator'])
                ? [...$keys, ...$this->extractFieldKeys($node)]
                : [...$keys, $node['field_key']];
        }

        return array_values(array_unique($keys));
    }
}
