<?php

declare(strict_types=1);

namespace App\Domain\CheckIn\Actions;

use App\Domain\CheckIn\Models\SeatingConstraint;
use App\Domain\CheckIn\Models\SeatingConstraintType;
use App\Domain\Organization\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

/**
 * Normalise la paire (guest_a, guest_b) dans un ordre stable avant
 * d'écrire : une contrainte entre A et B ne doit jamais pouvoir être
 * enregistrée une seconde fois dans l'ordre inverse (B, A), ce que la
 * contrainte unique en base ne détecterait pas sans cette normalisation.
 */
final class CreateSeatingConstraint
{
    public function handle(
        Organization $organization,
        int $eventId,
        string $guestAType,
        int $guestAId,
        string $guestBType,
        int $guestBId,
        SeatingConstraintType $type,
        User $user,
    ): SeatingConstraint {
        Gate::forUser($user)->authorize('updateGuests', $organization);

        if ($guestAType === $guestBType && $guestAId === $guestBId) {
            throw new InvalidArgumentException('Un invité ne peut pas être contraint avec lui-même.');
        }

        [$firstType, $firstId, $secondType, $secondId] = $this->normalize($guestAType, $guestAId, $guestBType, $guestBId);

        return SeatingConstraint::query()->updateOrCreate(
            [
                'event_id' => $eventId,
                'guest_a_type' => $firstType,
                'guest_a_id' => $firstId,
                'guest_b_type' => $secondType,
                'guest_b_id' => $secondId,
            ],
            [
                'organization_id' => $organization->id,
                'type' => $type,
            ],
        );
    }

    /**
     * @return array{0: string, 1: int, 2: string, 3: int}
     */
    private function normalize(string $aType, int $aId, string $bType, int $bId): array
    {
        $a = "{$aType}:{$aId}";
        $b = "{$bType}:{$bId}";

        return $a <= $b ? [$aType, $aId, $bType, $bId] : [$bType, $bId, $aType, $aId];
    }
}
