<?php

declare(strict_types=1);

namespace App\Domain\Ticketing\Models;

enum PromoCodeKind: string
{
    case Percent = 'percent';
    case Amount = 'amount';

    public function label(): string
    {
        return match ($this) {
            self::Percent => 'Pourcentage',
            self::Amount => 'Montant fixe',
        };
    }
}
