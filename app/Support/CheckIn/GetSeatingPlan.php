<?php

declare(strict_types=1);

namespace App\Support\CheckIn;

use App\Domain\CheckIn\Data\GuestData;
use App\Domain\CheckIn\Data\SeatingPlanData;
use App\Domain\CheckIn\Data\SeatingTableData;
use App\Domain\CheckIn\Models\SeatAssignment;
use App\Domain\CheckIn\Models\SeatingTable;
use App\Domain\Event\Models\Event;

/**
 * Assemble le plan de table d'un événement : tables, invités affectés à
 * chacune, invités encore non affectés — traverse Domain/CheckIn (tables et
 * affectations) et la liste unifiée d'invités (GetEventGuestList, elle-même
 * déjà hors de Domain/Form et Domain/Ticketing). Vit ici pour la même
 * raison que GetEventGuestList (voir son docblock).
 */
final class GetSeatingPlan
{
    public function __construct(
        private readonly GetEventGuestList $getEventGuestList,
    ) {}

    public function handle(Event $event): SeatingPlanData
    {
        $guests = $this->getEventGuestList->handle($event);
        $guestByKey = collect($guests)->keyBy(fn (GuestData $guest): string => "{$guest->guestType}:{$guest->id}");

        $tables = SeatingTable::query()->where('event_id', $event->id)->orderBy('id')->get();
        $assignments = SeatAssignment::query()->where('event_id', $event->id)->get();
        $assignedKeys = [];

        $tableData = $tables->map(function (SeatingTable $table) use ($assignments, $guestByKey, &$assignedKeys): SeatingTableData {
            $tableGuests = $assignments
                ->where('seating_table_id', $table->id)
                ->map(function (SeatAssignment $assignment) use ($guestByKey, &$assignedKeys): ?GuestData {
                    $key = "{$assignment->guest_type}:{$assignment->guest_id}";
                    $assignedKeys[$key] = true;

                    return $guestByKey->get($key);
                })
                ->filter()
                ->values()
                ->all();

            return new SeatingTableData(
                id: $table->id,
                name: $table->name,
                shape: $table->shape->value,
                capacity: $table->capacity,
                positionX: $table->position_x,
                positionY: $table->position_y,
                width: $table->width,
                height: $table->height,
                rotation: $table->rotation,
                guests: $tableGuests,
            );
        })->all();

        $unassignedGuests = collect($guests)
            ->reject(fn (GuestData $guest): bool => isset($assignedKeys["{$guest->guestType}:{$guest->id}"]))
            ->values()
            ->all();

        return new SeatingPlanData(tables: $tableData, unassignedGuests: $unassignedGuests);
    }
}
