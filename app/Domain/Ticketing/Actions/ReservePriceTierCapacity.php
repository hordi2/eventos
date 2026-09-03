<?php

declare(strict_types=1);

namespace App\Domain\Ticketing\Actions;

use App\Domain\Ticketing\Models\PriceTier;
use App\Support\Capacity\Actions\ReserveCapacity;
use App\Support\Capacity\Data\ReservationResult;

/**
 * Point d'entrée capacité pour un palier de tarification (moteur générique
 * réutilisé tel quel, cf. commit T-024). Jamais de liste d'attente ici : un
 * palier épuisé ne met personne en attente, il bascule simplement vers le
 * palier suivant (DetermineActivePriceTier) — contrairement à un événement.
 */
final class ReservePriceTierCapacity
{
    public function __construct(private readonly ReserveCapacity $reserveCapacity) {}

    public function handle(PriceTier $tier, string $reservationKey, int $quantity = 1): ReservationResult
    {
        return $this->reserveCapacity->handle(
            organizationId: $tier->organization_id,
            holderType: 'price_tier',
            holderId: (string) $tier->id,
            capacityLimit: $tier->quantity,
            reservationKey: $reservationKey,
            quantity: $quantity,
            allowWaitlist: false,
        );
    }
}
