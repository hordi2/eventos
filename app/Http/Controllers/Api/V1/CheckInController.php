<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\CheckIn\Actions\RecordCheckIn;
use App\Domain\CheckIn\Actions\SyncCheckIns;
use App\Domain\CheckIn\Data\CheckInScanData;
use App\Domain\CheckIn\Models\CheckInDirection;
use App\Domain\Event\Models\Event;
use App\Domain\Form\InvalidAttendeeQrTokenException;
use App\Domain\Ticketing\InvalidQrTokenException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\RecordCheckInRequest;
use App\Http\Requests\Api\V1\SyncCheckInsRequest;
use App\Http\Resources\Api\V1\CheckInResource;
use App\Http\Resources\Api\V1\CheckInResultResource;
use App\Http\Resources\Api\V1\GuestResource;
use App\Support\CheckIn\GetEventGuestList;
use App\Support\CheckIn\GuestExistsForEvent;
use App\Support\CheckIn\ResolveScannedGuest;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CheckInController extends Controller
{
    public function guests(Request $request, GetEventGuestList $getEventGuestList): JsonResponse
    {
        /** @var Event $event */
        $event = $request->attributes->get('checkInEvent');

        $guests = $getEventGuestList->handle($event, $request->string('q')->toString() ?: null);

        return GuestResource::collection($guests)->response();
    }

    public function store(
        RecordCheckInRequest $request,
        RecordCheckIn $recordCheckIn,
        ResolveScannedGuest $resolveScannedGuest,
        GuestExistsForEvent $guestExistsForEvent,
    ): JsonResponse {
        /** @var Event $event */
        $event = $request->attributes->get('checkInEvent');

        $attendeeId = $request->integer('attendee_id') ?: null;
        $ticketId = $request->integer('ticket_id') ?: null;
        $qrToken = $request->string('qr_token')->toString() ?: null;

        if ($qrToken !== null) {
            // Seul ce que le jeton résout fait foi : attendee_id/ticket_id
            // fournis par l'appareil ne restent qu'une indication optimiste
            // tant qu'un jeton est présent (même raisonnement que SyncCheckIns).
            try {
                $resolved = $resolveScannedGuest->handle($qrToken);
            } catch (InvalidQrTokenException|InvalidAttendeeQrTokenException $exception) {
                return response()->json(['error' => $exception->getMessage()], 422);
            }

            $attendeeId = $resolved['type'] === 'attendee' ? $resolved['id'] : null;
            $ticketId = $resolved['type'] === 'ticket' ? $resolved['id'] : null;

            if (! $guestExistsForEvent->handle($event->id, $attendeeId, $ticketId)) {
                return response()->json(['error' => "Ce QR code n'appartient pas à cet événement."], 422);
            }
        }

        $checkIn = $recordCheckIn->handle(
            organizationId: $event->organization_id,
            eventId: $event->id,
            attendeeId: $attendeeId,
            ticketId: $ticketId,
            deviceLocalId: $request->string('device_local_id')->toString(),
            direction: CheckInDirection::from($request->string('direction')->toString()),
            recordedAt: CarbonImmutable::parse($request->string('recorded_at')->toString()),
            checkedInBy: $request->user()?->id,
        );

        return CheckInResource::make($checkIn)->response()->setStatusCode(201);
    }

    public function sync(SyncCheckInsRequest $request, SyncCheckIns $syncCheckIns): JsonResponse
    {
        /** @var Event $event */
        $event = $request->attributes->get('checkInEvent');

        $scans = array_map(
            fn (array $scan): CheckInScanData => new CheckInScanData(
                attendeeId: isset($scan['attendee_id']) ? (int) $scan['attendee_id'] : null,
                ticketId: isset($scan['ticket_id']) ? (int) $scan['ticket_id'] : null,
                deviceLocalId: $scan['device_local_id'],
                qrToken: $scan['qr_token'] ?? null,
                direction: CheckInDirection::from($scan['direction']),
                recordedAt: CarbonImmutable::parse($scan['recorded_at']),
            ),
            $request->array('scans'),
        );

        $results = $syncCheckIns->handle(
            organizationId: $event->organization_id,
            eventId: $event->id,
            scans: $scans,
            checkedInBy: $request->user()?->id,
        );

        return CheckInResultResource::collection($results)->response();
    }
}
