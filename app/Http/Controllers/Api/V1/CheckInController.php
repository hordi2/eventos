<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\CheckIn\Actions\RecordCheckIn;
use App\Domain\CheckIn\Actions\SyncCheckIns;
use App\Domain\CheckIn\Data\CheckInScanData;
use App\Domain\CheckIn\Models\CheckInDirection;
use App\Domain\Event\Models\Event;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\RecordCheckInRequest;
use App\Http\Requests\Api\V1\SyncCheckInsRequest;
use App\Http\Resources\Api\V1\CheckInResource;
use App\Http\Resources\Api\V1\CheckInResultResource;
use App\Http\Resources\Api\V1\GuestResource;
use App\Support\CheckIn\GetEventGuestList;
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

    public function store(RecordCheckInRequest $request, RecordCheckIn $recordCheckIn): JsonResponse
    {
        /** @var Event $event */
        $event = $request->attributes->get('checkInEvent');

        $checkIn = $recordCheckIn->handle(
            organizationId: $event->organization_id,
            eventId: $event->id,
            attendeeId: $request->integer('attendee_id') ?: null,
            ticketId: $request->integer('ticket_id') ?: null,
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
