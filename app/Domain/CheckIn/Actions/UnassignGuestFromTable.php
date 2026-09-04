<?php

declare(strict_types=1);

namespace App\Domain\CheckIn\Actions;

use App\Domain\CheckIn\Models\SeatAssignment;
use App\Domain\Organization\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

final class UnassignGuestFromTable
{
    public function handle(Organization $organization, int $eventId, string $guestType, int $guestId, User $user): void
    {
        Gate::forUser($user)->authorize('updateGuests', $organization);

        SeatAssignment::query()
            ->where('event_id', $eventId)
            ->where('guest_type', $guestType)
            ->where('guest_id', $guestId)
            ->delete();
    }
}
