<?php

declare(strict_types=1);

namespace App\Support\Capacity\Actions;

use App\Support\Capacity\Models\CapacityHold;
use App\Support\Capacity\Models\CapacityHoldStatus;

/**
 * Places restantes sur un holder, pour l'affichage (ex. griser une option
 * complète — M2.4). La page qui l'affichera réellement (RSVP invité, T-031)
 * n'existe pas encore : cette classe expose seulement la donnée.
 */
final class GetRemainingCapacity
{
    public function handle(string $holderType, string $holderId, ?int $capacityLimit): ?int
    {
        if ($capacityLimit === null) {
            return null;
        }

        $held = (int) CapacityHold::query()
            ->where('holder_type', $holderType)
            ->where('holder_id', $holderId)
            ->where('status', CapacityHoldStatus::Held)
            ->sum('quantity');

        return max(0, $capacityLimit - $held);
    }

    public function isFull(string $holderType, string $holderId, ?int $capacityLimit): bool
    {
        return $this->handle($holderType, $holderId, $capacityLimit) === 0;
    }
}
