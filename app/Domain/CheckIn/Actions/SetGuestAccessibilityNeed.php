<?php

declare(strict_types=1);

namespace App\Domain\CheckIn\Actions;

use App\Domain\CheckIn\Models\SeatingAccessibilityNeed;
use App\Domain\Organization\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * Besoin d'accès PMR posé par l'organisateur (jamais déclaré par l'invité
 * lui-même, section 1 du CLAUDE.md : voir le docblock de la migration).
 */
final class SetGuestAccessibilityNeed
{
    public function handle(Organization $organization, int $eventId, string $guestType, int $guestId, bool $needsAccessibleSeating, User $user): void
    {
        Gate::forUser($user)->authorize('updateGuests', $organization);

        if ($needsAccessibleSeating) {
            SeatingAccessibilityNeed::query()->firstOrCreate([
                'event_id' => $eventId,
                'guest_type' => $guestType,
                'guest_id' => $guestId,
            ], [
                'organization_id' => $organization->id,
            ]);

            return;
        }

        SeatingAccessibilityNeed::query()
            ->where('event_id', $eventId)
            ->where('guest_type', $guestType)
            ->where('guest_id', $guestId)
            ->delete();
    }
}
