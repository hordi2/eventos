<?php

declare(strict_types=1);

namespace App\Domain\CheckIn\Actions;

use App\Domain\CheckIn\Data\AutoPlaceResult;

/**
 * Placement automatique assisté (AC de T-065) : heuristique gloutonne,
 * pas un solveur de contraintes exact — sur le volume d'un plan de salle
 * réel (quelques centaines d'invités), un résultat de bon niveau immédiat
 * vaut mieux qu'un calcul optimal trop lent ou trop complexe à maintenir.
 * L'organisateur garde de toute façon la main pour corriger à la souris
 * ensuite (AssignGuestToTable).
 *
 * Étapes : regroupe d'abord les invités liés par une contrainte
 * "doit être avec" (union-find, les groupes voyagent ensemble ou pas du
 * tout) ; place les plus gros groupes en premier (plus difficiles à caser) ;
 * pour chaque groupe, choisit la table dont la place restante après
 * placement serait la plus faible parmi celles qui peuvent l'accueillir en
 * entier sans y placer deux invités "ne doit pas être avec" — équilibrage
 * du remplissage en tassant les tables plutôt qu'en les étalant.
 *
 * Ne reçoit et ne renvoie que des tableaux de scalaires : aucune requête ni
 * dépendance à un modèle d'un autre module (section 3 du CLAUDE.md),
 * l'assemblage des données d'entrée et la persistance des résultats
 * reviennent à l'appelant (voir App\Http\Controllers\Organizer\SeatingController).
 */
final class AutoPlaceGuests
{
    /**
     * @param  list<array{guest_type: string, guest_id: int}>  $unassignedGuests
     * @param  list<array{id: int, remaining: int}>  $tables
     * @param  list<array{guest_a: string, guest_b: string, type: string}>  $constraints
     */
    public function handle(array $unassignedGuests, array $tables, array $constraints): AutoPlaceResult
    {
        $mustNotBeWith = $this->buildMustNotBeWithMap($constraints);
        $groups = $this->groupByMustBeWith($unassignedGuests, $constraints);

        usort($groups, fn (array $a, array $b): int => count($b) <=> count($a));

        $remaining = collect($tables)->keyBy('id')->map(fn (array $table): int => $table['remaining'])->all();
        $seatedAt = []; // "type:id" => table_id, pour vérifier les conflits au fil du placement.
        $assignments = [];
        $unplaced = [];

        foreach ($groups as $group) {
            $tableId = $this->findBestTable($group, $remaining, $seatedAt, $mustNotBeWith);

            if ($tableId === null) {
                array_push($unplaced, ...$group);

                continue;
            }

            foreach ($group as $guest) {
                $key = $this->guestKey($guest);
                $seatedAt[$key] = $tableId;
                $remaining[$tableId]--;
                $assignments[] = [
                    'guest_type' => $guest['guest_type'],
                    'guest_id' => $guest['guest_id'],
                    'seating_table_id' => $tableId,
                ];
            }
        }

        return new AutoPlaceResult(assignments: $assignments, unplaced: $unplaced);
    }

    /**
     * @param  list<array{guest_a: string, guest_b: string, type: string}>  $constraints
     * @return array<string, list<string>>
     */
    private function buildMustNotBeWithMap(array $constraints): array
    {
        $map = [];

        foreach ($constraints as $constraint) {
            if ($constraint['type'] !== 'must_not_be_with') {
                continue;
            }

            $map[$constraint['guest_a']][] = $constraint['guest_b'];
            $map[$constraint['guest_b']][] = $constraint['guest_a'];
        }

        return $map;
    }

    /**
     * Union-find minimal : chaque invité démarre seul dans son groupe, une
     * contrainte "doit être avec" fusionne les deux groupes concernés.
     *
     * @param  list<array{guest_type: string, guest_id: int}>  $guests
     * @param  list<array{guest_a: string, guest_b: string, type: string}>  $constraints
     * @return list<list<array{guest_type: string, guest_id: int}>>
     */
    private function groupByMustBeWith(array $guests, array $constraints): array
    {
        $guestByKey = [];
        $parent = [];

        foreach ($guests as $guest) {
            $key = $this->guestKey($guest);
            $guestByKey[$key] = $guest;
            $parent[$key] = $key;
        }

        $find = function (string $key) use (&$parent, &$find): string {
            if ($parent[$key] !== $key) {
                $parent[$key] = $find($parent[$key]);
            }

            return $parent[$key];
        };

        foreach ($constraints as $constraint) {
            if ($constraint['type'] !== 'must_be_with') {
                continue;
            }

            if (! isset($parent[$constraint['guest_a']]) || ! isset($parent[$constraint['guest_b']])) {
                continue; // Un des deux invités est déjà assis manuellement, ou n'existe pas dans le lot.
            }

            $rootA = $find($constraint['guest_a']);
            $rootB = $find($constraint['guest_b']);
            $parent[$rootA] = $rootB;
        }

        $groups = [];

        foreach (array_keys($guestByKey) as $key) {
            $groups[$find($key)][] = $guestByKey[$key];
        }

        return array_values($groups);
    }

    /**
     * @param  list<array{guest_type: string, guest_id: int}>  $group
     * @param  array<int, int>  $remaining
     * @param  array<string, int>  $seatedAt
     * @param  array<string, list<string>>  $mustNotBeWith
     */
    private function findBestTable(array $group, array $remaining, array $seatedAt, array $mustNotBeWith): ?int
    {
        $best = null;
        $bestLeftover = null;

        foreach ($remaining as $tableId => $capacity) {
            if ($capacity < count($group)) {
                continue;
            }

            if ($this->hasConflict($group, $tableId, $seatedAt, $mustNotBeWith)) {
                continue;
            }

            $leftover = $capacity - count($group);

            if ($bestLeftover === null || $leftover < $bestLeftover) {
                $best = $tableId;
                $bestLeftover = $leftover;
            }
        }

        return $best;
    }

    /**
     * @param  list<array{guest_type: string, guest_id: int}>  $group
     * @param  array<string, int>  $seatedAt
     * @param  array<string, list<string>>  $mustNotBeWith
     */
    private function hasConflict(array $group, int $tableId, array $seatedAt, array $mustNotBeWith): bool
    {
        foreach ($group as $guest) {
            $key = $this->guestKey($guest);

            foreach ($mustNotBeWith[$key] ?? [] as $forbiddenKey) {
                if (($seatedAt[$forbiddenKey] ?? null) === $tableId) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param  array{guest_type: string, guest_id: int}  $guest
     */
    private function guestKey(array $guest): string
    {
        return "{$guest['guest_type']}:{$guest['guest_id']}";
    }
}
