<?php

declare(strict_types=1);

namespace App\Domain\Event\Models;

/**
 * Nature d'une ligne de budget (D7) : ce qui sort, ou ce qui rentre hors
 * billetterie (sponsors, subventions). Les recettes de la billetterie ne se
 * saisissent jamais à la main : elles sont lues dans les commandes payées.
 */
enum BudgetLineKind: string
{
    case Expense = 'expense';
    case Income = 'income';

    public function label(): string
    {
        return match ($this) {
            self::Expense => 'Dépense',
            self::Income => 'Recette',
        };
    }
}
