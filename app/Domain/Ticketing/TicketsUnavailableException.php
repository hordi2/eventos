<?php

declare(strict_types=1);

namespace App\Domain\Ticketing;

use RuntimeException;

final class TicketsUnavailableException extends RuntimeException
{
    public static function quotaReached(int $ticketTypeId): self
    {
        return new self("Le type de billet #{$ticketTypeId} a atteint son quota global.");
    }

    public static function noActivePriceTier(int $ticketTypeId): self
    {
        return new self("Le type de billet #{$ticketTypeId} n'a aucun palier de tarification actif (vente fermée ou tous les paliers épuisés).");
    }

    public static function tierQuotaReached(int $priceTierId): self
    {
        return new self("Le palier de tarification #{$priceTierId} a atteint son quota.");
    }
}
