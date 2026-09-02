<?php

declare(strict_types=1);

namespace App\Support\Capacity\Data;

final class ReservationResult
{
    private function __construct(
        public readonly ReservationOutcome $outcome,
        public readonly ?int $waitlistPosition,
    ) {}

    public static function accepted(): self
    {
        return new self(ReservationOutcome::Accepted, null);
    }

    public static function waitlisted(int $position): self
    {
        return new self(ReservationOutcome::Waitlisted, $position);
    }

    public static function rejected(): self
    {
        return new self(ReservationOutcome::Rejected, null);
    }
}
