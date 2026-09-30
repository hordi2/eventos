<?php

declare(strict_types=1);

namespace App\Support\Budget;

use App\Domain\Event\Models\BudgetLine;
use App\Support\Money;

/**
 * Budget d'un événement, tel qu'il s'affiche (D7).
 */
final class EventBudgetData
{
    /**
     * @param  list<BudgetLine>  $lines
     */
    public function __construct(
        public readonly string $currency,
        public readonly Money $plannedExpenses,
        public readonly Money $actualExpenses,
        public readonly Money $plannedIncomes,
        public readonly Money $actualIncomes,
        public readonly Money $ticketingRevenue,
        public readonly Money $result,
        public readonly Money $toCover,
        public readonly ?int $ticketsToSell,
        public readonly int $overrunCount,
        public readonly array $lines,
    ) {}
}
