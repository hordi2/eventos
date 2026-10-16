<?php

declare(strict_types=1);

namespace App\Support\Agency;

use App\Domain\Organization\Models\PlanTier;
use App\Support\Money;

/**
 * Un compte client vu depuis le portail de son agence (D10). plan et
 * wholesaleCost sont ce que ce compte coûte à l'agence (facturation
 * consolidée, voir ManageClientSubscriptionItems) ; resalePrice est ce
 * qu'elle facture elle-même à son client, jamais traité par Itaza (voir
 * SetClientResalePrice) — les deux montants ne sont comparables que de la
 * même devise.
 */
final class ClientAccountData
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $slug,
        public readonly int $eventCount,
        public readonly ?string $nextEventTitle,
        public readonly ?string $nextEventDate,
        public readonly int $registrationCount,
        public readonly Money $revenue,
        public readonly ?string $managedSince,
        public readonly PlanTier $plan,
        public readonly Money $wholesaleCost,
        public readonly ?Money $resalePrice,
    ) {}

    public function margin(): ?Money
    {
        if ($this->resalePrice === null || ! $this->resalePrice->isSameCurrency($this->wholesaleCost)) {
            return null;
        }

        return $this->resalePrice->subtract($this->wholesaleCost);
    }
}
