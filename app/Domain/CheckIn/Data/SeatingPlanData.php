<?php

declare(strict_types=1);

namespace App\Domain\CheckIn\Data;

final class SeatingPlanData
{
    /**
     * @param  list<SeatingTableData>  $tables
     * @param  list<GuestData>  $unassignedGuests
     */
    public function __construct(
        public readonly array $tables,
        public readonly array $unassignedGuests,
    ) {}
}
