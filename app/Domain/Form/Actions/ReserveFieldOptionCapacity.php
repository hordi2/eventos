<?php

declare(strict_types=1);

namespace App\Domain\Form\Actions;

use App\Domain\Form\Models\FieldOption;
use App\Support\Capacity\Actions\ReserveCapacity;
use App\Support\Capacity\Data\ReservationResult;

/**
 * Point d'entrée capacité pour le quota d'une option de champ (M2.1 : « Options
 * avec quota individuel »). Le CDC (M2.4) ne prévoit pas de liste d'attente au
 * niveau d'une option — seulement « griser l'option » une fois complète — donc
 * allowWaitlist reste toujours false ici, contrairement à ReserveEventCapacity.
 */
final class ReserveFieldOptionCapacity
{
    public function __construct(private readonly ReserveCapacity $reserveCapacity) {}

    public function handle(FieldOption $option, string $reservationKey, int $quantity = 1): ReservationResult
    {
        return $this->reserveCapacity->handle(
            organizationId: $option->organization_id,
            holderType: 'form_field_option',
            holderId: (string) $option->id,
            capacityLimit: $option->quota,
            reservationKey: $reservationKey,
            quantity: $quantity,
            allowWaitlist: false,
        );
    }
}
