<?php

declare(strict_types=1);

namespace App\Support\Budget;

use App\Domain\Event\Models\BudgetLine;
use App\Domain\Event\Models\Event;
use App\Support\Money;

/**
 * Budget d'un événement tel que l'écran l'affiche (D7) : des montants déjà
 * formatés pour la lecture, et leur valeur brute pour le formulaire.
 */
final class PresentEventBudget
{
    public function __construct(
        private readonly GetEventBudget $getEventBudget,
    ) {}

    /**
     * @return array{currency: string, totals: array<string, string>, result: array{amount: string, isPositive: bool}, toCover: array{amount: string, isCovered: bool, ticketsToSell: ?int}, overrunCount: int, lines: list<array<string, mixed>>}
     */
    public function handle(Event $event): array
    {
        $budget = $this->getEventBudget->handle($event);

        return [
            'currency' => $budget->currency,
            'totals' => [
                'plannedExpenses' => $budget->plannedExpenses->format(),
                'actualExpenses' => $budget->actualExpenses->format(),
                'plannedIncomes' => $budget->plannedIncomes->format(),
                'actualIncomes' => $budget->actualIncomes->format(),
                'ticketingRevenue' => $budget->ticketingRevenue->format(),
            ],
            'result' => [
                'amount' => $budget->result->format(),
                'isPositive' => ! $budget->result->isNegative(),
            ],
            'toCover' => [
                'amount' => $budget->toCover->format(),
                'isCovered' => $budget->toCover->isZero(),
                'ticketsToSell' => $budget->ticketsToSell,
            ],
            'overrunCount' => $budget->overrunCount,
            'lines' => array_map($this->line(...), $budget->lines),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function line(BudgetLine $line): array
    {
        return [
            'id' => $line->id,
            'kind' => $line->kind->value,
            'category' => $line->category->value,
            'categoryLabel' => $line->category->label(),
            'label' => $line->label,
            'supplier' => $line->supplier,
            'note' => $line->note,
            'planned' => $line->planned->format(),
            'actual' => $line->actual?->format(),
            // Valeurs brutes du formulaire : ce que la personne a écrit,
            // pas le montant mis en forme avec son symbole monétaire.
            'plannedInput' => $this->input($line->planned),
            'actualInput' => $line->actual === null ? '' : $this->input($line->actual),
            'isOverrun' => $line->isOverrun(),
        ];
    }

    /**
     * Montant réécrit en unités de la devise, sans séparateur ni symbole :
     * calcul entier, jamais de float (règle 4.2).
     */
    private function input(Money $amount): string
    {
        $decimals = Money::decimals($amount->currency());
        $minor = $amount->amountMinor();

        if ($decimals === 0) {
            return (string) $minor;
        }

        $divider = 10 ** $decimals;

        return intdiv($minor, $divider).','.str_pad((string) ($minor % $divider), $decimals, '0', STR_PAD_LEFT);
    }
}
