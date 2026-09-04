<?php

declare(strict_types=1);

namespace App\Http\Controllers\Organizer;

use App\Domain\CheckIn\Actions\AssignGuestToTable;
use App\Domain\CheckIn\Actions\CreateSeatingConstraint;
use App\Domain\CheckIn\Actions\CreateSeatingTable;
use App\Domain\CheckIn\Actions\DeleteSeatingConstraint;
use App\Domain\CheckIn\Actions\DeleteSeatingTable;
use App\Domain\CheckIn\Actions\UnassignGuestFromTable;
use App\Domain\CheckIn\Actions\UpdateSeatingTable;
use App\Domain\CheckIn\Data\GuestData;
use App\Domain\CheckIn\Data\SeatingTableData;
use App\Domain\CheckIn\Models\SeatingConstraint;
use App\Domain\CheckIn\Models\SeatingConstraintType;
use App\Domain\CheckIn\Models\SeatingTable;
use App\Domain\CheckIn\Models\SeatingTableShape;
use App\Domain\CheckIn\SeatingTableFullException;
use App\Domain\Event\Models\Event;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organizer\Seating\AssignGuestRequest;
use App\Http\Requests\Organizer\Seating\StoreSeatingConstraintRequest;
use App\Http\Requests\Organizer\Seating\StoreSeatingTableRequest;
use App\Http\Requests\Organizer\Seating\UpdateSeatingTableRequest;
use App\Support\CheckIn\GetSeatingPlan;
use App\Support\CheckIn\RunAutoPlacement;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

final class SeatingController extends Controller
{
    public function index(int $event, GetSeatingPlan $getSeatingPlan): InertiaResponse
    {
        $event = $this->findEvent($event);
        Gate::authorize('viewGuests', $event->organization);

        $plan = $getSeatingPlan->handle($event);
        $constraints = SeatingConstraint::query()->where('event_id', $event->id)->get();

        return Inertia::render('Seating/Show', [
            'event' => ['id' => $event->id, 'title' => $event->title],
            'tables' => array_map($this->serializeTable(...), $plan->tables),
            'unassignedGuests' => array_map($this->serializeGuest(...), $plan->unassignedGuests),
            'constraints' => $constraints->map(fn (SeatingConstraint $constraint): array => [
                'id' => $constraint->id,
                'guest_a_type' => $constraint->guest_a_type,
                'guest_a_id' => $constraint->guest_a_id,
                'guest_b_type' => $constraint->guest_b_type,
                'guest_b_id' => $constraint->guest_b_id,
                'type' => $constraint->type->value,
            ]),
            'shapes' => array_map(fn (SeatingTableShape $shape): string => $shape->value, SeatingTableShape::cases()),
        ]);
    }

    public function storeTable(int $event, StoreSeatingTableRequest $request, CreateSeatingTable $action): JsonResponse
    {
        $event = $this->findEvent($event);

        $table = $action->handle(
            organization: $event->organization,
            eventId: $event->id,
            shape: SeatingTableShape::from($request->string('shape')->toString()),
            capacity: $request->integer('capacity'),
            user: $request->user(),
            name: $request->string('name')->toString() ?: null,
        );

        return response()->json($this->serializeTable(new SeatingTableData(
            id: $table->id,
            name: $table->name,
            shape: $table->shape->value,
            capacity: $table->capacity,
            positionX: $table->position_x,
            positionY: $table->position_y,
            width: $table->width,
            height: $table->height,
            rotation: $table->rotation,
            guests: [],
        )), 201);
    }

    public function updateTable(int $event, int $table, UpdateSeatingTableRequest $request, UpdateSeatingTable $action): JsonResponse
    {
        $event = $this->findEvent($event);
        $tableModel = SeatingTable::query()->where('event_id', $event->id)->findOrFail($table);

        $updated = $action->handle(
            table: $tableModel,
            user: $request->user(),
            name: $request->string('name')->toString() ?: null,
            shape: $request->has('shape') ? SeatingTableShape::from($request->string('shape')->toString()) : null,
            capacity: $request->has('capacity') ? $request->integer('capacity') : null,
            positionX: $request->has('position_x') ? $request->float('position_x') : null,
            positionY: $request->has('position_y') ? $request->float('position_y') : null,
            width: $request->has('width') ? $request->float('width') : null,
            height: $request->has('height') ? $request->float('height') : null,
            rotation: $request->has('rotation') ? $request->float('rotation') : null,
        );

        return response()->json([
            'id' => $updated->id,
            'name' => $updated->name,
            'shape' => $updated->shape->value,
            'capacity' => $updated->capacity,
            'position_x' => $updated->position_x,
            'position_y' => $updated->position_y,
            'width' => $updated->width,
            'height' => $updated->height,
            'rotation' => $updated->rotation,
        ]);
    }

    public function destroyTable(int $event, int $table, Request $request, DeleteSeatingTable $action): JsonResponse
    {
        $event = $this->findEvent($event);
        $tableModel = SeatingTable::query()->where('event_id', $event->id)->findOrFail($table);

        $action->handle($tableModel, $request->user());

        return response()->json(null, 204);
    }

    public function assign(int $event, int $table, AssignGuestRequest $request, AssignGuestToTable $action): JsonResponse
    {
        $event = $this->findEvent($event);
        $tableModel = SeatingTable::query()->where('event_id', $event->id)->findOrFail($table);

        try {
            $action->handle($tableModel, $request->string('guest_type')->toString(), $request->integer('guest_id'), $request->user());
        } catch (SeatingTableFullException $exception) {
            return response()->json(['error' => $exception->getMessage()], 422);
        }

        return response()->json(null, 204);
    }

    public function unassign(int $event, Request $request, UnassignGuestFromTable $action): JsonResponse
    {
        $event = $this->findEvent($event);

        $action->handle(
            $event->organization,
            $event->id,
            $request->string('guest_type')->toString(),
            $request->integer('guest_id'),
            $request->user(),
        );

        return response()->json(null, 204);
    }

    public function autoPlace(int $event, RunAutoPlacement $action): JsonResponse
    {
        $event = $this->findEvent($event);
        Gate::authorize('updateGuests', $event->organization);

        $result = $action->handle($event->organization, $event);

        return response()->json([
            'placed_count' => count($result->assignments),
            'unplaced_count' => count($result->unplaced),
        ]);
    }

    public function storeConstraint(int $event, StoreSeatingConstraintRequest $request, CreateSeatingConstraint $action): JsonResponse
    {
        $event = $this->findEvent($event);

        $constraint = $action->handle(
            organization: $event->organization,
            eventId: $event->id,
            guestAType: $request->string('guest_a_type')->toString(),
            guestAId: $request->integer('guest_a_id'),
            guestBType: $request->string('guest_b_type')->toString(),
            guestBId: $request->integer('guest_b_id'),
            type: SeatingConstraintType::from($request->string('type')->toString()),
            user: $request->user(),
        );

        return response()->json([
            'id' => $constraint->id,
            'guest_a_type' => $constraint->guest_a_type,
            'guest_a_id' => $constraint->guest_a_id,
            'guest_b_type' => $constraint->guest_b_type,
            'guest_b_id' => $constraint->guest_b_id,
            'type' => $constraint->type->value,
        ], 201);
    }

    public function destroyConstraint(int $event, int $constraint, Request $request, DeleteSeatingConstraint $action): JsonResponse
    {
        $event = $this->findEvent($event);
        $constraintModel = SeatingConstraint::query()->where('event_id', $event->id)->findOrFail($constraint);

        $action->handle($constraintModel, $request->user());

        return response()->json(null, 204);
    }

    public function exportPlan(int $event, GetSeatingPlan $getSeatingPlan): Response
    {
        $event = $this->findEvent($event);
        Gate::authorize('viewGuests', $event->organization);

        $plan = $getSeatingPlan->handle($event);

        $pdf = Pdf::loadView('seating.plan-pdf', ['event' => $event, 'tables' => $plan->tables])->output();

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"plan-de-salle-{$event->id}.pdf\"",
        ]);
    }

    public function exportLists(int $event, GetSeatingPlan $getSeatingPlan): Response
    {
        $event = $this->findEvent($event);
        Gate::authorize('viewGuests', $event->organization);

        $plan = $getSeatingPlan->handle($event);

        $pdf = Pdf::loadView('seating.lists-pdf', ['event' => $event, 'tables' => $plan->tables])->output();

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"listes-par-table-{$event->id}.pdf\"",
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeTable(SeatingTableData $table): array
    {
        return [
            'id' => $table->id,
            'name' => $table->name,
            'shape' => $table->shape,
            'capacity' => $table->capacity,
            'position_x' => $table->positionX,
            'position_y' => $table->positionY,
            'width' => $table->width,
            'height' => $table->height,
            'rotation' => $table->rotation,
            'guests' => array_map($this->serializeGuest(...), $table->guests),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeGuest(GuestData $guest): array
    {
        return [
            'guest_type' => $guest->guestType,
            'id' => $guest->id,
            'name' => $guest->name,
        ];
    }

    private function findEvent(int $id): Event
    {
        return Event::query()->findOrFail($id);
    }
}
