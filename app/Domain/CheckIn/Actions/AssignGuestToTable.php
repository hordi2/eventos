<?php

declare(strict_types=1);

namespace App\Domain\CheckIn\Actions;

use App\Domain\CheckIn\Models\SeatAssignment;
use App\Domain\CheckIn\Models\SeatingTable;
use App\Domain\CheckIn\SeatingTableFullException;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * « Un invité n'est jamais affecté à deux tables à la fois » (AC de T-065) :
 * toute affectation précédente du même invité pour cet événement est
 * supprimée avant de créer la nouvelle, dans la même transaction — jamais
 * deux lignes simultanées pour le même invité (contrainte unique en
 * dernier recours si cette logique était contournée).
 */
final class AssignGuestToTable
{
    public function handle(SeatingTable $table, string $guestType, int $guestId, User $user): SeatAssignment
    {
        Gate::forUser($user)->authorize('updateGuests', $table->organization);

        return DB::transaction(function () use ($table, $guestType, $guestId): SeatAssignment {
            SeatAssignment::query()
                ->where('event_id', $table->event_id)
                ->where('guest_type', $guestType)
                ->where('guest_id', $guestId)
                ->delete();

            $occupied = SeatAssignment::query()->where('seating_table_id', $table->id)->count();

            if ($occupied >= $table->capacity) {
                throw SeatingTableFullException::forTable($table->id);
            }

            return SeatAssignment::query()->create([
                'organization_id' => $table->organization_id,
                'event_id' => $table->event_id,
                'seating_table_id' => $table->id,
                'guest_type' => $guestType,
                'guest_id' => $guestId,
            ]);
        });
    }
}
