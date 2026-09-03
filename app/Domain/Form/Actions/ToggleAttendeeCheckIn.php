<?php

declare(strict_types=1);

namespace App\Domain\Form\Actions;

use App\Domain\Form\Models\Attendee;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Gate;

/**
 * Pointage manuel (T-042) en attendant le vrai check-in par scan QR, hors
 * ligne, avec device_local_id (T-060/061) — cette action n'a pas vocation à
 * survivre telle quelle une fois ces tickets construits.
 */
final class ToggleAttendeeCheckIn
{
    public function handle(Attendee $attendee, User $actor): Attendee
    {
        Gate::forUser($actor)->authorize('checkIn', $attendee->organization);

        $attendee->update([
            'checked_in_at' => $attendee->checked_in_at === null ? CarbonImmutable::now() : null,
        ]);

        return $attendee;
    }
}
