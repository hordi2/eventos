<?php

declare(strict_types=1);

namespace App\Domain\Ticketing\Data;

use App\Support\Money;

final class CashReportData
{
    /**
     * @param  list<CashReportEntryData>  $entries
     */
    public function __construct(
        public readonly Money $total,
        public readonly int $count,
        public readonly array $entries,
    ) {}
}
