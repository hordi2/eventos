<?php

declare(strict_types=1);

namespace App\Support\CheckIn;

use App\Domain\CheckIn\Actions\AutoPlaceGuests;
use App\Domain\CheckIn\Data\AutoPlaceResult;
use App\Domain\CheckIn\Data\GuestData;
use App\Domain\CheckIn\Models\SeatAssignment;
use App\Domain\CheckIn\Models\SeatingConstraint;
use App\Domain\CheckIn\Models\SeatingTable;
use App\Domain\Event\Models\Event;
use App\Domain\Organization\Models\Organization;
use Illuminate\Support\Facades\DB;

/**
 * Assemble les données réelles (invités non affectés, capacité restante par
 * table, contraintes) pour AutoPlaceGuests (Domain/CheckIn, algorithme pur)
 * puis persiste le résultat — même séparation que RecordCheckIn/
 * SyncCheckIns : l'algorithme ne connaît que des tableaux de scalaires,
 * l'assemblage cross-domaine et l'écriture restent hors de Domain/CheckIn.
 */
final class RunAutoPlacement
{
    public function __construct(
        private readonly GetSeatingPlan $getSeatingPlan,
        private readonly AutoPlaceGuests $autoPlaceGuests,
    ) {}

    public function handle(Organization $organization, Event $event): AutoPlaceResult
    {
        $plan = $this->getSeatingPlan->handle($event);

        $unassignedGuests = array_map(
            fn (GuestData $guest): array => ['guest_type' => $guest->guestType, 'guest_id' => $guest->id],
            $plan->unassignedGuests,
        );

        $tables = SeatingTable::query()
            ->where('event_id', $event->id)
            ->get()
            ->map(fn (SeatingTable $table): array => [
                'id' => $table->id,
                'remaining' => $table->capacity - SeatAssignment::query()->where('seating_table_id', $table->id)->count(),
            ])
            ->filter(fn (array $table): bool => $table['remaining'] > 0)
            ->values()
            ->all();

        $constraints = SeatingConstraint::query()
            ->where('event_id', $event->id)
            ->get()
            ->map(fn (SeatingConstraint $constraint): array => [
                'guest_a' => "{$constraint->guest_a_type}:{$constraint->guest_a_id}",
                'guest_b' => "{$constraint->guest_b_type}:{$constraint->guest_b_id}",
                'type' => $constraint->type->value,
            ])
            ->all();

        $result = $this->autoPlaceGuests->handle($unassignedGuests, $tables, $constraints);

        DB::transaction(function () use ($result, $organization, $event): void {
            foreach ($result->assignments as $assignment) {
                SeatAssignment::query()->create([
                    'organization_id' => $organization->id,
                    'event_id' => $event->id,
                    'seating_table_id' => $assignment['seating_table_id'],
                    'guest_type' => $assignment['guest_type'],
                    'guest_id' => $assignment['guest_id'],
                ]);
            }
        });

        return $result;
    }
}
