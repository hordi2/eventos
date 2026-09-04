<?php

declare(strict_types=1);

namespace App\Support\Dashboard;

final class DashboardStatsData
{
    /**
     * @param  list<array{date: string, cumulative: int}>  $registrationCurve
     * @param  list<array{hour: string, count: int}>  $arrivalCurve
     */
    public function __construct(
        public readonly int $confirmedCount,
        public readonly int $presentCount,
        public readonly float $presenceRate,
        public readonly array $registrationCurve,
        public readonly array $arrivalCurve,
    ) {}
}
