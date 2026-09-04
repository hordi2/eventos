<?php

declare(strict_types=1);

namespace App\Domain\CheckIn\Models;

enum SeatingTableShape: string
{
    case Round = 'round';
    case Rectangular = 'rectangular';
    case UShape = 'u_shape';
    case Cocktail = 'cocktail';
    case Rows = 'rows';
}
