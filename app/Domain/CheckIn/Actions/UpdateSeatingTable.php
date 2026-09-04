<?php

declare(strict_types=1);

namespace App\Domain\CheckIn\Actions;

use App\Domain\CheckIn\Models\SeatingTable;
use App\Domain\CheckIn\Models\SeatingTableShape;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * Un seul point d'entrée pour toute modification de table (renommage,
 * forme, capacité, position/taille dans l'éditeur visuel) plutôt qu'une
 * action par champ — cohérent avec le reste du domaine (voir
 * UpdateTicketType).
 */
final class UpdateSeatingTable
{
    public function handle(
        SeatingTable $table,
        User $user,
        ?string $name = null,
        ?SeatingTableShape $shape = null,
        ?int $capacity = null,
        ?float $positionX = null,
        ?float $positionY = null,
        ?float $width = null,
        ?float $height = null,
        ?float $rotation = null,
    ): SeatingTable {
        Gate::forUser($user)->authorize('updateGuests', $table->organization);

        $table->update(array_filter([
            'name' => $name,
            'shape' => $shape,
            'capacity' => $capacity,
            'position_x' => $positionX,
            'position_y' => $positionY,
            'width' => $width,
            'height' => $height,
            'rotation' => $rotation,
        ], fn (mixed $value): bool => $value !== null));

        return $table->fresh();
    }
}
