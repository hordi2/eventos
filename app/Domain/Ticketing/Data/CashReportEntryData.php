<?php

declare(strict_types=1);

namespace App\Domain\Ticketing\Data;

use App\Support\Money;
use Carbon\CarbonImmutable;

final class CashReportEntryData
{
    public function __construct(
        public readonly int $paymentId,
        public readonly int $orderId,
        public readonly Money $amount,
        public readonly ?string $collectorName,
        public readonly CarbonImmutable $collectedAt,
    ) {}
}
