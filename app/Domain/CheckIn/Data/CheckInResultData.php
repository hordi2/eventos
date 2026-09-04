<?php

declare(strict_types=1);

namespace App\Domain\CheckIn\Data;

use App\Domain\CheckIn\Models\CheckInStatus;

final class CheckInResultData
{
    public function __construct(
        public readonly string $deviceLocalId,
        public readonly int $checkInId,
        public readonly CheckInStatus $status,
    ) {}
}
