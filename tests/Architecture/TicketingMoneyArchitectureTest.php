<?php

declare(strict_types=1);

use App\Domain\Ticketing\Models\PriceTier;
use App\Domain\Ticketing\Models\TicketType;
use App\Support\Casts\AsMoney;

// M5.1 / T-050, §4.2 CLAUDE.md : aucun montant en float ni decimal, ni en
// base ni côté PHP — tout passe par le value object Money via le cast
// AsMoney, qui n'occupe que des colonnes entières (amount_minor + currency).

it('caste le montant d\'un palier de tarification via Money (AsMoney), jamais un float', function (): void {
    expect((new PriceTier)->getCasts()['amount'])->toBe(AsMoney::class);
});

it('aucun cast float ou decimal dans les modèles du module Ticketing', function (): void {
    foreach ([TicketType::class, PriceTier::class] as $modelClass) {
        foreach ((new $modelClass)->getCasts() as $attribute => $cast) {
            expect($cast)->not->toBe('float')
                ->and($cast)->not->toStartWith('decimal:')
                ->and($cast)->not->toBe('double');
        }
    }
});

it('les migrations de billetterie ne déclarent aucune colonne monétaire en float ou decimal', function (): void {
    $files = glob(dirname(__DIR__, 2).'/database/migrations/*_create_ticket_*_table.php');

    expect($files)->not->toBeEmpty();

    foreach ($files as $file) {
        $contents = file_get_contents($file);

        expect($contents)
            ->not->toContain('->float(')
            ->not->toContain('->double(')
            ->not->toContain('->decimal(')
            ->not->toContain('->unsignedDecimal(');
    }
});
