<?php

declare(strict_types=1);

namespace App\Domain\Event\Actions;

use App\Domain\Event\Models\Event;
use App\Support\Capacity\Actions\ReleaseCapacity;

final class ReleaseEventCapacity
{
    public function __construct(private readonly ReleaseCapacity $releaseCapacity) {}

    public function handle(Event $event, string $reservationKey): void
    {
        $this->releaseCapacity->handle('event', (string) $event->id, $reservationKey);
    }
}
