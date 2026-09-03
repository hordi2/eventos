<?php

declare(strict_types=1);

namespace App\Domain\Ticketing\Actions;

use App\Domain\Ticketing\Models\TicketType;
use App\Support\Capacity\Actions\ReserveCapacity;
use App\Support\Capacity\Data\ReservationResult;

/**
 * Quota global d'un type de billet (TicketType.total_quantity), tous
 * paliers confondus — indépendant du quota propre à chaque palier
 * (ReservePriceTierCapacity). Un billet payant consomme les deux ; un
 * billet gratuit ne consomme que celui-ci, faute de palier.
 */
final class ReserveTicketTypeCapacity
{
    public function __construct(private readonly ReserveCapacity $reserveCapacity) {}

    public function handle(TicketType $ticketType, string $reservationKey, int $quantity = 1): ReservationResult
    {
        return $this->reserveCapacity->handle(
            organizationId: $ticketType->organization_id,
            holderType: 'ticket_type',
            holderId: (string) $ticketType->id,
            capacityLimit: $ticketType->total_quantity,
            reservationKey: $reservationKey,
            quantity: $quantity,
            allowWaitlist: false,
        );
    }
}
