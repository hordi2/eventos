<?php

declare(strict_types=1);

namespace App\Domain\CheckIn\Actions;

use App\Domain\CheckIn\Models\SeatAssignment;
use App\Domain\CheckIn\Models\SeatingTable;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * Supprimer une table libère ses invités plutôt que de les laisser
 * orphelins d'une table supprimée : ils repassent dans le vivier des
 * invités non affectés, jamais dans un état incohérent.
 */
final class DeleteSeatingTable
{
    public function handle(SeatingTable $table, User $user): void
    {
        Gate::forUser($user)->authorize('updateGuests', $table->organization);

        SeatAssignment::query()->where('seating_table_id', $table->id)->delete();
        $table->delete();
    }
}
