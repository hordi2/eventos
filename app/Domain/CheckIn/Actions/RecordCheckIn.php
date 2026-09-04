<?php

declare(strict_types=1);

namespace App\Domain\CheckIn\Actions;

use App\Domain\CheckIn\Models\CheckIn;
use App\Domain\CheckIn\Models\CheckInDirection;
use App\Domain\CheckIn\Models\CheckInStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Idempotence (§4.4 du CLAUDE.md) : device_local_id est unique, un même scan
 * rejoué à la resynchronisation renvoie toujours la même ligne, jamais un
 * doublon.
 *
 * Résolution de conflit (M7.1.4) : premier scan Accepted retenu pour un
 * invité donné, tout scan suivant sur la même direction est marqué Conflict
 * (jamais rejeté : l'appareil doit quand même savoir que son scan a été
 * synchronisé, juste signalé comme doublon).
 *
 * Le verrou Redis englobe sa propre transaction DB et rien d'autre : il ne
 * doit jamais être imbriqué dans une transaction plus large, sous peine de
 * relâcher le verrou avant le COMMIT réel (bug de concurrence trouvé et
 * corrigé sur CreateOrder, T-051 — voir son docblock).
 */
final class RecordCheckIn
{
    public function handle(
        int $organizationId,
        int $eventId,
        ?int $attendeeId,
        ?int $ticketId,
        string $deviceLocalId,
        CheckInDirection $direction,
        CarbonImmutable $recordedAt,
        ?int $checkedInBy,
    ): CheckIn {
        $existing = CheckIn::query()->where('device_local_id', $deviceLocalId)->first();

        if ($existing !== null) {
            return $existing;
        }

        $guestKey = $attendeeId !== null ? "attendee:{$attendeeId}" : "ticket:{$ticketId}";

        return Cache::lock("check-in:{$guestKey}:{$direction->value}", 10)->block(5, function () use (
            $organizationId,
            $eventId,
            $attendeeId,
            $ticketId,
            $deviceLocalId,
            $direction,
            $recordedAt,
            $checkedInBy,
        ): CheckIn {
            return DB::transaction(function () use (
                $organizationId,
                $eventId,
                $attendeeId,
                $ticketId,
                $deviceLocalId,
                $direction,
                $recordedAt,
                $checkedInBy,
            ): CheckIn {
                $alreadyAccepted = CheckIn::query()
                    ->when($attendeeId !== null, fn ($query) => $query->where('attendee_id', $attendeeId))
                    ->when($ticketId !== null, fn ($query) => $query->where('ticket_id', $ticketId))
                    ->where('direction', $direction)
                    ->where('status', CheckInStatus::Accepted)
                    ->exists();

                try {
                    return CheckIn::query()->create([
                        'organization_id' => $organizationId,
                        'event_id' => $eventId,
                        'attendee_id' => $attendeeId,
                        'ticket_id' => $ticketId,
                        'device_local_id' => $deviceLocalId,
                        'direction' => $direction,
                        'status' => $alreadyAccepted ? CheckInStatus::Conflict : CheckInStatus::Accepted,
                        'recorded_at' => $recordedAt,
                        'checked_in_by' => $checkedInBy,
                    ]);
                } catch (UniqueConstraintViolationException) {
                    // Le même device_local_id est arrivé deux fois en
                    // parallèle (retry réseau) : la ligne existe déjà,
                    // idempotence garantie par la contrainte unique.
                    return CheckIn::query()->where('device_local_id', $deviceLocalId)->firstOrFail();
                }
            });
        });
    }
}
