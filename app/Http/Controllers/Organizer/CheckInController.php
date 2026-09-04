<?php

declare(strict_types=1);

namespace App\Http\Controllers\Organizer;

use App\Domain\CheckIn\Actions\RecordCheckIn;
use App\Domain\CheckIn\Data\GuestData;
use App\Domain\CheckIn\Models\CheckInDirection;
use App\Domain\Event\Models\Event;
use App\Domain\Ticketing\Actions\AddWalkInGuest;
use App\Domain\Ticketing\Actions\DetermineActivePriceTier;
use App\Domain\Ticketing\Actions\VerifyTicketQrToken;
use App\Domain\Ticketing\InvalidQrTokenException;
use App\Domain\Ticketing\Models\TicketType;
use App\Domain\Ticketing\TicketsUnavailableException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organizer\CheckIn\AddWalkInGuestRequest;
use App\Http\Requests\Organizer\CheckIn\RecordCheckInRequest;
use App\Http\Requests\Organizer\CheckIn\ScanTicketRequest;
use App\Support\CheckIn\GetEventGuestList;
use App\Support\CheckIn\GuestExistsForEvent;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

/**
 * Check-in web de secours (T-062) : poste fixe (ordinateur portable) pour
 * les organisateurs déjà connectés au back-office — pas de jeton Sanctum
 * ici, contrairement à l'API mobile (T-060) qu'il réutilise en interne
 * (mêmes actions Domain/CheckIn), juste la session organisateur habituelle.
 */
final class CheckInController extends Controller
{
    public function index(int $event, GetEventGuestList $getEventGuestList, DetermineActivePriceTier $determineActivePriceTier): Response
    {
        $event = $this->findEvent($event);
        Gate::authorize('checkIn', $event->organization);

        $guests = $getEventGuestList->handle($event);

        $ticketTypes = TicketType::query()
            ->where('event_id', $event->id)
            ->where('is_active', true)
            ->orderBy('position')
            ->get()
            ->map(function (TicketType $ticketType) use ($determineActivePriceTier): ?array {
                if ($ticketType->is_free) {
                    return ['id' => $ticketType->id, 'name' => $ticketType->name, 'is_free' => true, 'price' => null];
                }

                $tier = $determineActivePriceTier->handle($ticketType);

                if ($tier === null) {
                    return null;
                }

                return ['id' => $ticketType->id, 'name' => $ticketType->name, 'is_free' => false, 'price' => $tier->amount->format()];
            })
            ->filter()
            ->values();

        return Inertia::render('CheckIn/Show', [
            'event' => ['id' => $event->id, 'title' => $event->title],
            'guests' => array_map($this->serializeGuest(...), $guests),
            'ticketTypes' => $ticketTypes,
        ]);
    }

    public function walkIn(
        int $event,
        AddWalkInGuestRequest $request,
        AddWalkInGuest $addWalkInGuest,
        RecordCheckIn $recordCheckIn,
        GetEventGuestList $getEventGuestList,
    ): JsonResponse {
        $event = $this->findEvent($event);
        Gate::authorize('checkIn', $event->organization);

        try {
            $order = $addWalkInGuest->handle(
                organization: $event->organization,
                eventId: $event->id,
                ticketTypeId: $request->integer('ticket_type_id'),
                name: $request->string('name')->toString(),
                email: $request->string('email')->toString(),
                phone: $request->string('phone')->toString() ?: null,
                collector: $request->user(),
            );
        } catch (TicketsUnavailableException|InvalidArgumentException $exception) {
            return response()->json(['error' => $exception->getMessage()], 422);
        }

        $ticket = $order->items->first()?->tickets->first();

        if ($ticket === null) {
            return response()->json(['error' => "Le billet n'a pas pu être émis."], 422);
        }

        $checkIn = $recordCheckIn->handle(
            organizationId: $event->organization_id,
            eventId: $event->id,
            attendeeId: null,
            ticketId: $ticket->id,
            deviceLocalId: (string) Str::uuid(),
            direction: CheckInDirection::CheckIn,
            recordedAt: CarbonImmutable::now(),
            checkedInBy: $request->user()?->id,
        );

        $guest = $getEventGuestList->findOne($event, 'ticket', $ticket->id);

        return response()->json([
            'status' => $checkIn->status->value,
            'guest' => $guest !== null ? $this->serializeGuest($guest) : null,
        ]);
    }

    public function scan(
        int $event,
        ScanTicketRequest $request,
        VerifyTicketQrToken $verifyTicketQrToken,
        RecordCheckIn $recordCheckIn,
        GuestExistsForEvent $guestExistsForEvent,
        GetEventGuestList $getEventGuestList,
    ): JsonResponse {
        $event = $this->findEvent($event);
        Gate::authorize('checkIn', $event->organization);

        try {
            $ticket = $verifyTicketQrToken->handle($request->string('token')->toString());
        } catch (InvalidQrTokenException $exception) {
            return response()->json(['error' => $exception->getMessage()], 422);
        }

        if (! $guestExistsForEvent->handle($event->id, null, $ticket->id)) {
            return response()->json(['error' => "Ce billet n'appartient pas à cet événement."], 422);
        }

        $checkIn = $recordCheckIn->handle(
            organizationId: $event->organization_id,
            eventId: $event->id,
            attendeeId: null,
            ticketId: $ticket->id,
            deviceLocalId: (string) Str::uuid(),
            direction: CheckInDirection::CheckIn,
            recordedAt: CarbonImmutable::now(),
            checkedInBy: $request->user()?->id,
        );

        $guest = $getEventGuestList->findOne($event, 'ticket', $ticket->id);

        return response()->json([
            'status' => $checkIn->status->value,
            'guest' => $guest !== null ? $this->serializeGuest($guest) : null,
        ]);
    }

    public function record(
        int $event,
        RecordCheckInRequest $request,
        RecordCheckIn $recordCheckIn,
        GuestExistsForEvent $guestExistsForEvent,
        GetEventGuestList $getEventGuestList,
    ): JsonResponse {
        $event = $this->findEvent($event);
        Gate::authorize('checkIn', $event->organization);

        $guestType = $request->string('guest_type')->toString();
        $id = $request->integer('id');
        $attendeeId = $guestType === 'attendee' ? $id : null;
        $ticketId = $guestType === 'ticket' ? $id : null;

        if (! $guestExistsForEvent->handle($event->id, $attendeeId, $ticketId)) {
            return response()->json(['error' => "Cet invité n'appartient pas à cet événement."], 422);
        }

        $checkIn = $recordCheckIn->handle(
            organizationId: $event->organization_id,
            eventId: $event->id,
            attendeeId: $attendeeId,
            ticketId: $ticketId,
            deviceLocalId: (string) Str::uuid(),
            direction: CheckInDirection::CheckIn,
            recordedAt: CarbonImmutable::now(),
            checkedInBy: $request->user()?->id,
        );

        $guest = $getEventGuestList->findOne($event, $guestType, $id);

        return response()->json([
            'status' => $checkIn->status->value,
            'guest' => $guest !== null ? $this->serializeGuest($guest) : null,
        ]);
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
            'email' => $guest->email,
            'phone' => $guest->phone,
            'checked_in' => $guest->checkedIn,
            'checked_in_at' => $guest->checkedInAt,
        ];
    }

    private function findEvent(int $id): Event
    {
        return Event::query()->findOrFail($id);
    }
}
