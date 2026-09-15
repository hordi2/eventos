<?php

declare(strict_types=1);

namespace App\Support\CheckIn;

use App\Domain\Form\Models\RegistrationStatus;
use Illuminate\Support\Facades\DB;

/**
 * Le QR d'une personne est émis pour son inscription principale. À
 * l'entrée d'un événement secondaire, il désigne la même personne (même
 * rang) dans l'inscription rattachée à cette session (T-013), pour qu'un
 * seul QR serve à toutes les sessions choisies.
 */
final class ResolveAttendeeForEvent
{
    public function handle(int $eventId, int $attendeeId): int
    {
        $scanned = DB::table('attendees')
            ->join('registrations', 'registrations.id', '=', 'attendees.registration_id')
            ->where('attendees.id', $attendeeId)
            ->first(['attendees.position', 'registrations.id as registration_id', 'registrations.event_id']);

        if ($scanned === null || (int) $scanned->event_id === $eventId) {
            return $attendeeId;
        }

        $sessionAttendeeId = DB::table('attendees')
            ->join('registrations', 'registrations.id', '=', 'attendees.registration_id')
            ->where('registrations.parent_registration_id', $scanned->registration_id)
            ->where('registrations.event_id', $eventId)
            ->where('registrations.status', RegistrationStatus::Confirmed->value)
            ->whereNull('registrations.deleted_at')
            ->whereNull('attendees.deleted_at')
            ->where('attendees.position', $scanned->position)
            ->orderByDesc('registrations.id')
            ->value('attendees.id');

        return $sessionAttendeeId !== null ? (int) $sessionAttendeeId : $attendeeId;
    }
}
