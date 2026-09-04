<?php

declare(strict_types=1);

namespace App\Domain\CheckIn\Actions;

use App\Domain\CheckIn\Models\SeatingTable;
use App\Domain\CheckIn\Models\SeatingTableShape;
use App\Domain\Organization\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * « Numérotation automatique des tables » (AC de T-065) : le nom par défaut
 * suit le nombre de tables déjà créées pour l'événement, l'organisateur
 * reste libre de le renommer ensuite (le champ n'est pas verrouillé).
 */
final class CreateSeatingTable
{
    public function handle(
        Organization $organization,
        int $eventId,
        SeatingTableShape $shape,
        int $capacity,
        User $user,
        ?string $name = null,
    ): SeatingTable {
        Gate::forUser($user)->authorize('updateGuests', $organization);

        $nextNumber = SeatingTable::query()->where('event_id', $eventId)->count() + 1;

        // Toutes les colonnes explicitement fixées ici, même celles qui ont
        // une valeur par défaut en base : create() ne relit pas les valeurs
        // par défaut de la base pour les colonnes non précisées, l'objet en
        // mémoire les aurait sinon à null malgré le défaut réel en base
        // (même piège que fees_absorbed, T-050).
        return SeatingTable::query()->create([
            'organization_id' => $organization->id,
            'event_id' => $eventId,
            'name' => $name ?? "Table {$nextNumber}",
            'shape' => $shape,
            'capacity' => $capacity,
            'position_x' => 40,
            'position_y' => 40,
            'width' => 120,
            'height' => 120,
            'rotation' => 0,
        ]);
    }
}
