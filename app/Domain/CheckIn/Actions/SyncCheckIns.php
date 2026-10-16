<?php

declare(strict_types=1);

namespace App\Domain\CheckIn\Actions;

use App\Domain\CheckIn\Data\CheckInResultData;
use App\Domain\CheckIn\Data\CheckInScanData;
use App\Domain\CheckIn\Models\CheckInStatus;
use App\Domain\Form\InvalidAttendeeQrTokenException;
use App\Domain\Ticketing\InvalidQrTokenException;
use App\Support\CheckIn\GuestExistsForEvent;
use App\Support\CheckIn\ResolveScannedGuest;

/**
 * Point d'entrée de la synchronisation par lot (T-060) : chaque scan garde
 * son idempotence propre via device_local_id (RecordCheckIn), un scan en
 * échec dans le lot n'empêche donc jamais les autres d'être synchronisés.
 *
 * Un scan qui porte un jeton QR (scanné, par opposition à une entrée
 * choisie dans la liste, sans jeton) le fait vérifier avant d'enregistrer
 * quoi que ce soit — signature, expiration, statut, non-réutilisation
 * (§4.6 CLAUDE.md). Le poste mobile n'a par construction aucun moyen de
 * vérifier cette signature hors ligne (ResolveScannedGuest), c'est bien ce
 * moment-ci, à la resynchronisation, qui porte la vérification réelle :
 * un jeton forgé, un billet déjà annulé ou un QR réémis depuis sont
 * rejetés ici, jamais acceptés silencieusement. attendee_id/ticket_id
 * fournis par l'appareil ne sont qu'une indication optimiste tant qu'un
 * jeton est présent : seul ce que le jeton résout fait foi.
 */
final class SyncCheckIns
{
    public function __construct(
        private readonly RecordCheckIn $recordCheckIn,
        private readonly ResolveScannedGuest $resolveScannedGuest,
        private readonly GuestExistsForEvent $guestExistsForEvent,
    ) {}

    /**
     * @param  list<CheckInScanData>  $scans
     * @return list<CheckInResultData>
     */
    public function handle(int $organizationId, int $eventId, array $scans, ?int $checkedInBy): array
    {
        return array_map(
            fn (CheckInScanData $scan): CheckInResultData => $this->processScan($organizationId, $eventId, $scan, $checkedInBy),
            $scans,
        );
    }

    private function processScan(int $organizationId, int $eventId, CheckInScanData $scan, ?int $checkedInBy): CheckInResultData
    {
        $attendeeId = $scan->attendeeId;
        $ticketId = $scan->ticketId;

        if ($scan->qrToken !== null) {
            try {
                $resolved = $this->resolveScannedGuest->handle($scan->qrToken);
            } catch (InvalidQrTokenException|InvalidAttendeeQrTokenException $exception) {
                return new CheckInResultData(
                    deviceLocalId: $scan->deviceLocalId,
                    checkInId: null,
                    status: CheckInStatus::Rejected,
                    error: $exception->getMessage(),
                );
            }

            $attendeeId = $resolved['type'] === 'attendee' ? $resolved['id'] : null;
            $ticketId = $resolved['type'] === 'ticket' ? $resolved['id'] : null;

            if (! $this->guestExistsForEvent->handle($eventId, $attendeeId, $ticketId)) {
                return new CheckInResultData(
                    deviceLocalId: $scan->deviceLocalId,
                    checkInId: null,
                    status: CheckInStatus::Rejected,
                    error: "Ce QR code n'appartient pas à cet événement.",
                );
            }
        }

        $checkIn = $this->recordCheckIn->handle(
            organizationId: $organizationId,
            eventId: $eventId,
            attendeeId: $attendeeId,
            ticketId: $ticketId,
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
    }
}
