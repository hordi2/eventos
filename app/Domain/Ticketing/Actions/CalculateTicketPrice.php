<?php

declare(strict_types=1);

namespace App\Domain\Ticketing\Actions;

use App\Domain\Ticketing\Data\TicketPriceBreakdownData;
use App\Domain\Ticketing\Models\PriceTier;
use App\Domain\Ticketing\Models\TicketType;
use App\Domain\Ticketing\Models\VatMode;
use App\Support\Money;

/**
 * Décompose le prix d'un palier en net / TVA / brut selon le régime du type
 * de billet (M5.1 : « TVA paramétrable, incluse ou en sus »).
 *
 * Le taux est en points de base (vat_rate_bp, 2000 = 20,00 %) et l'arrondi se
 * fait entièrement en entiers (arrondi au centime le plus proche, moitié
 * arrondie au-dessus) — jamais de float (§4.2 CLAUDE.md, T-050).
 */
final class CalculateTicketPrice
{
    public function handle(PriceTier $tier, TicketType $ticketType): TicketPriceBreakdownData
    {
        $amount = $tier->amount;

        return match ($ticketType->vat_mode) {
            VatMode::None => new TicketPriceBreakdownData(
                net: $amount,
                vat: Money::zero($amount->currency()),
                gross: $amount,
            ),
            VatMode::Excluded => $this->fromNet($amount, $ticketType->vat_rate_bp),
            VatMode::Included => $this->fromGross($amount, $ticketType->vat_rate_bp),
        };
    }

    private function fromNet(Money $net, int $vatRateBp): TicketPriceBreakdownData
    {
        $vat = Money::fromMinorUnits(
            $this->roundHalfUp($net->amountMinor() * $vatRateBp, 10_000),
            $net->currency(),
        );

        return new TicketPriceBreakdownData(net: $net, vat: $vat, gross: $net->add($vat));
    }

    private function fromGross(Money $gross, int $vatRateBp): TicketPriceBreakdownData
    {
        $vat = Money::fromMinorUnits(
            $this->roundHalfUp($gross->amountMinor() * $vatRateBp, 10_000 + $vatRateBp),
            $gross->currency(),
        );

        return new TicketPriceBreakdownData(net: $gross->subtract($vat), vat: $vat, gross: $gross);
    }

    /**
     * Arrondi au plus proche, moitié arrondie au-dessus — sans jamais passer
     * par un float. Valide uniquement pour numérateur/dénominateur positifs
     * ou nuls, seul cas rencontré ici (montants et taux de TVA).
     */
    private function roundHalfUp(int $numerator, int $denominator): int
    {
        return intdiv(2 * $numerator + $denominator, 2 * $denominator);
    }
}
