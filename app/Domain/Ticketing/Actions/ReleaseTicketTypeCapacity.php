<?php

declare(strict_types=1);

namespace App\Domain\Ticketing\Actions;

use App\Domain\Ticketing\Models\TicketType;
use App\Support\Capacity\Actions\ReleaseCapacity;

final class ReleaseTicketTypeCapacity
{
    public function __construct(private readonly ReleaseCapacity $releaseCapacity) {}

    public function handle(TicketType $ticketType, string $reservationKey): void
    {
        $this->releaseCapacity->handle('ticket_type', (string) $ticketType->id, $reservationKey);
    }
}
