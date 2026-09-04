<?php

declare(strict_types=1);

namespace App\Domain\CheckIn\Data;

use App\Domain\CheckIn\Models\CheckInDirection;
use Carbon\CarbonImmutable;

final class CheckInScanData
{
    public function __construct(
        public readonly ?int $attendeeId,
        public readonly ?int $ticketId,
        public readonly string $deviceLocalId,
        public readonly CheckInDirection $direction,
        public readonly CarbonImmutable $recordedAt,
    ) {}
}
