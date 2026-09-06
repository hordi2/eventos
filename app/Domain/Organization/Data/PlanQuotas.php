<?php

declare(strict_types=1);

namespace App\Domain\Organization\Data;

final class PlanQuotas
{
    public function __construct(
        public readonly ?int $registrationsPerMonth,
        public readonly ?int $emailsPerMonth,
        public readonly ?int $activeEvents,
    ) {}
}
