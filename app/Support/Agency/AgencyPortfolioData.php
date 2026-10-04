<?php

declare(strict_types=1);

namespace App\Support\Agency;

use App\Support\Money;

/**
 * Le portefeuille d'une agence : ses comptes clients et leur total (D10).
 */
final class AgencyPortfolioData
{
    /**
     * @param  list<ClientAccountData>  $clients
     */
    public function __construct(
        public readonly array $clients,
        public readonly int $eventCount,
        public readonly int $registrationCount,
        public readonly Money $revenue,
    ) {}
}
