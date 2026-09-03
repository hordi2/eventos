<?php

declare(strict_types=1);

namespace App\Domain\Ticketing\Actions;

use App\Domain\Ticketing\Models\PriceTier;
use App\Support\Capacity\Actions\ReleaseCapacity;

final class ReleasePriceTierCapacity
{
    public function __construct(private readonly ReleaseCapacity $releaseCapacity) {}

    public function handle(PriceTier $tier, string $reservationKey): void
    {
        $this->releaseCapacity->handle('price_tier', (string) $tier->id, $reservationKey);
    }
}
