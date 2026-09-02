<?php

declare(strict_types=1);

namespace App\Domain\Event\Actions;

use App\Domain\Event\Models\Event;
use App\Support\Capacity\Actions\ReserveCapacity;
use App\Support\Capacity\Data\ReservationResult;

/**
 * Point d'entrée capacité pour un événement — et, la structure étant la
 * même, pour un sous-événement (un sous-événement est un Event avec
 * parent_event_id renseigné, avec sa propre capacité indépendante, §M1.3).
 */
final class ReserveEventCapacity
{
    public function __construct(private readonly ReserveCapacity $reserveCapacity) {}

    public function handle(Event $event, string $reservationKey, int $quantity = 1): ReservationResult
    {
        return $this->reserveCapacity->handle(
            organizationId: $event->organization_id,
            holderType: 'event',
            holderId: (string) $event->id,
            capacityLimit: $event->capacity,
            reservationKey: $reservationKey,
            quantity: $quantity,
            allowWaitlist: $event->allow_waitlist,
        );
    }
}
