<?php

declare(strict_types=1);

namespace App\Domain\Ticketing\Data;

use App\Support\Money;

/**
 * net + vat = gross, toujours par construction : selon le régime, l'une des
 * deux composantes est calculée puis l'autre dérivée par addition ou
 * soustraction (jamais les deux calculées indépendamment), pour ne jamais
 * perdre ou faire apparaître un centime à l'arrondi (M5.1, T-050).
 */
final class TicketPriceBreakdownData
{
    public function __construct(
        public readonly Money $net,
        public readonly Money $vat,
        public readonly Money $gross,
    ) {}
}
