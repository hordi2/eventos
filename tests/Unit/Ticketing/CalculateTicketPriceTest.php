<?php

declare(strict_types=1);

use App\Domain\Ticketing\Actions\CalculateTicketPrice;
use App\Domain\Ticketing\Models\PriceTier;
use App\Domain\Ticketing\Models\TicketType;
use App\Domain\Ticketing\Models\VatMode;
use App\Support\Money;

function ticketTypeWithVat(VatMode $mode, int $rateBp): TicketType
{
    $ticketType = new TicketType;
    $ticketType->vat_mode = $mode;
    $ticketType->vat_rate_bp = $rateBp;

    return $ticketType;
}

function priceTierOf(int $amountMinor, string $currency = 'EUR'): PriceTier
{
    $tier = new PriceTier;
    $tier->amount = Money::fromMinorUnits($amountMinor, $currency);

    return $tier;
}

it('n\'applique aucune TVA quand le régime est "aucune"', function (): void {
    $breakdown = app(CalculateTicketPrice::class)->handle(priceTierOf(1000), ticketTypeWithVat(VatMode::None, 0));

    expect($breakdown->net->amountMinor())->toBe(1000);
    expect($breakdown->vat->amountMinor())->toBe(0);
    expect($breakdown->gross->amountMinor())->toBe(1000);
});

it('calcule une TVA en sus sans reste à arrondir', function (): void {
    $breakdown = app(CalculateTicketPrice::class)->handle(priceTierOf(1000), ticketTypeWithVat(VatMode::Excluded, 2000));

    expect($breakdown->net->amountMinor())->toBe(1000);
    expect($breakdown->vat->amountMinor())->toBe(200);
    expect($breakdown->gross->amountMinor())->toBe(1200);
});

it('calcule une TVA incluse et retrouve le même prix net que "en sus" sur le même brut', function (): void {
    $breakdown = app(CalculateTicketPrice::class)->handle(priceTierOf(1200), ticketTypeWithVat(VatMode::Included, 2000));

    expect($breakdown->net->amountMinor())->toBe(1000);
    expect($breakdown->vat->amountMinor())->toBe(200);
    expect($breakdown->gross->amountMinor())->toBe(1200);
});

it('arrondit la TVA en sus au centime le plus proche, moitié arrondie au-dessus', function (): void {
    // 999 * 20 % = 199,80 -> arrondi à 200.
    $breakdown = app(CalculateTicketPrice::class)->handle(priceTierOf(999), ticketTypeWithVat(VatMode::Excluded, 2000));

    expect($breakdown->vat->amountMinor())->toBe(200);
    expect($breakdown->net->amountMinor())->toBe(999);
    expect($breakdown->gross->amountMinor())->toBe(1199);
});

it('arrondit exactement une moitié de centime au-dessus', function (): void {
    // 1 centime * 50 % = 0,5 centime -> arrondi à 1.
    $breakdown = app(CalculateTicketPrice::class)->handle(priceTierOf(1), ticketTypeWithVat(VatMode::Excluded, 5000));

    expect($breakdown->vat->amountMinor())->toBe(1);
});

it('un taux à 0 % ne génère aucune TVA, incluse ou en sus', function (): void {
    $excluded = app(CalculateTicketPrice::class)->handle(priceTierOf(1000), ticketTypeWithVat(VatMode::Excluded, 0));
    $included = app(CalculateTicketPrice::class)->handle(priceTierOf(1000), ticketTypeWithVat(VatMode::Included, 0));

    expect($excluded->vat->amountMinor())->toBe(0);
    expect($included->vat->amountMinor())->toBe(0);
});

it('un montant nul ne génère aucune TVA', function (): void {
    $breakdown = app(CalculateTicketPrice::class)->handle(priceTierOf(0), ticketTypeWithVat(VatMode::Included, 2000));

    expect($breakdown->net->amountMinor())->toBe(0);
    expect($breakdown->vat->amountMinor())->toBe(0);
    expect($breakdown->gross->amountMinor())->toBe(0);
});

it('net + TVA = brut, toujours, quel que soit le régime', function (): void {
    $cases = [
        [VatMode::None, 0, 1337],
        [VatMode::Excluded, 550, 2599],
        [VatMode::Included, 1875, 4999],
        [VatMode::Excluded, 10000, 1],
    ];

    foreach ($cases as [$mode, $rateBp, $amountMinor]) {
        $breakdown = app(CalculateTicketPrice::class)->handle(priceTierOf($amountMinor), ticketTypeWithVat($mode, $rateBp));

        expect($breakdown->net->amountMinor() + $breakdown->vat->amountMinor())->toBe($breakdown->gross->amountMinor());
    }
});
