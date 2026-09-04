<?php

declare(strict_types=1);

namespace App\Domain\CheckIn\Actions;

use App\Domain\CheckIn\Data\CheckInResultData;
use App\Domain\CheckIn\Data\CheckInScanData;

/**
 * Point d'entrée de la synchronisation par lot (T-060) : chaque scan garde
 * son idempotence propre via device_local_id (RecordCheckIn), un scan en
 * échec dans le lot n'empêche donc jamais les autres d'être synchronisés.
 */
final class SyncCheckIns
{
    public function __construct(
        private readonly RecordCheckIn $recordCheckIn,
    ) {}

    /**
     * @param  list<CheckInScanData>  $scans
     * @return list<CheckInResultData>
     */
    public function handle(int $organizationId, int $eventId, array $scans, ?int $checkedInBy): array
    {
        return array_map(function (CheckInScanData $scan) use ($organizationId, $eventId, $checkedInBy): CheckInResultData {
            $checkIn = $this->recordCheckIn->handle(
                organizationId: $organizationId,
                eventId: $eventId,
                attendeeId: $scan->attendeeId,
                ticketId: $scan->ticketId,
                deviceLocalId: $scan->deviceLocalId,
                direction: $scan->direction,
                recordedAt: $scan->recordedAt,
                checkedInBy: $checkedInBy,
            );

            return new CheckInResultData(
                deviceLocalId: $checkIn->device_local_id,
                checkInId: $checkIn->id,
                status: $checkIn->status,
            );
        }, $scans);
    }
}
