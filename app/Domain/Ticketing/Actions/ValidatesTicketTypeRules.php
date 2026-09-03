<?php

declare(strict_types=1);

namespace App\Domain\Ticketing\Actions;

use App\Domain\Ticketing\Models\VatMode;
use InvalidArgumentException;

/**
 * Règles communes à CreateTicketType et UpdateTicketType — les deux doivent
 * rester cohérentes sur les mêmes invariants (M5.1), d'où l'extraction
 * plutôt qu'une duplication.
 */
trait ValidatesTicketTypeRules
{
    private function assertValidOrderBounds(int $minPerOrder, ?int $maxPerOrder): void
    {
        if ($minPerOrder < 1) {
            throw new InvalidArgumentException('La quantité minimale par commande doit être d\'au moins 1.');
        }

        if ($maxPerOrder !== null && $maxPerOrder < $minPerOrder) {
            throw new InvalidArgumentException('La quantité maximale par commande ne peut pas être inférieure au minimum.');
        }
    }

    private function assertValidVat(VatMode $vatMode, int $vatRateBp): void
    {
        if ($vatRateBp < 0) {
            throw new InvalidArgumentException('Le taux de TVA ne peut pas être négatif.');
        }

        if ($vatMode === VatMode::None && $vatRateBp !== 0) {
            throw new InvalidArgumentException('Un taux de TVA ne peut être renseigné sans régime de TVA (incluse/en sus).');
        }
    }
}
