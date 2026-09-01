<?php

declare(strict_types=1);

use App\Support\CurrencyMismatchException;
use App\Support\Money;

it('additionne deux montants de la même devise', function (): void {
    $sum = Money::fromMinorUnits(1000, 'EUR')->add(Money::fromMinorUnits(250, 'EUR'));

    expect($sum->amountMinor())->toBe(1250);
    expect($sum->currency())->toBe('EUR');
});

it('lève une exception en additionnant deux devises différentes', function (): void {
    Money::fromMinorUnits(1000, 'EUR')->add(Money::fromMinorUnits(250, 'USD'));
})->throws(CurrencyMismatchException::class);

it('lève une exception en soustrayant deux devises différentes', function (): void {
    Money::fromMinorUnits(1000, 'EUR')->subtract(Money::fromMinorUnits(250, 'USD'));
})->throws(CurrencyMismatchException::class);

it('lève une exception en comparant deux devises différentes', function (): void {
    Money::fromMinorUnits(1000, 'EUR')->greaterThan(Money::fromMinorUnits(250, 'USD'));
})->throws(CurrencyMismatchException::class);

it('répartit un montant sans jamais perdre un centime (100 / 3)', function (): void {
    $shares = Money::fromMinorUnits(100, 'EUR')->allocate([1, 1, 1]);

    expect(array_map(fn (Money $m) => $m->amountMinor(), $shares))->toBe([34, 33, 33]);
    expect(array_sum(array_map(fn (Money $m) => $m->amountMinor(), $shares)))->toBe(100);
});

it('répartit selon des ratios inégaux sans perte', function (): void {
    $shares = Money::fromMinorUnits(10_000, 'EUR')->allocate([2, 1]);

    $total = array_sum(array_map(fn (Money $m) => $m->amountMinor(), $shares));

    expect($total)->toBe(10_000);
    expect($shares[0]->amountMinor())->toBeGreaterThan($shares[1]->amountMinor());
});

it('répartit un montant négatif sans perte', function (): void {
    $shares = Money::fromMinorUnits(-100, 'EUR')->allocate([1, 1, 1]);

    expect(array_sum(array_map(fn (Money $m) => $m->amountMinor(), $shares)))->toBe(-100);
});

it('est immuable : chaque opération retourne une nouvelle instance', function (): void {
    $original = Money::fromMinorUnits(1000, 'EUR');
    $original->add(Money::fromMinorUnits(500, 'EUR'));

    expect($original->amountMinor())->toBe(1000);
});

it('compare l\'égalité par montant et devise', function (): void {
    expect(Money::fromMinorUnits(1000, 'EUR')->equals(Money::fromMinorUnits(1000, 'EUR')))->toBeTrue();
    expect(Money::fromMinorUnits(1000, 'EUR')->equals(Money::fromMinorUnits(1000, 'USD')))->toBeFalse();
    expect(Money::fromMinorUnits(1000, 'EUR')->equals(Money::fromMinorUnits(999, 'EUR')))->toBeFalse();
});

it('reconnaît zéro, positif et négatif', function (): void {
    expect(Money::zero('EUR')->isZero())->toBeTrue();
    expect(Money::fromMinorUnits(1, 'EUR')->isPositive())->toBeTrue();
    expect(Money::fromMinorUnits(-1, 'EUR')->isNegative())->toBeTrue();
});

it('rejette un code devise qui n\'est pas au format ISO 4217', function (): void {
    Money::fromMinorUnits(1000, 'euros');
})->throws(InvalidArgumentException::class);

it('formate correctement pour EUR, USD, CDF, XOF et XAF', function (): void {
    // 1050,50 dans les devises à 2 décimales ; 105 050 pour les devises
    // sans subdivision (XOF/XAF), pour la même valeur en unité mineure.
    expect(Money::fromMinorUnits(105_050, 'EUR')->format('fr'))
        ->toContain('1')->toContain('050,50')->toContain('€');

    expect(Money::fromMinorUnits(105_050, 'USD')->format('fr'))
        ->toContain('050,50')->toContain('$');

    expect(Money::fromMinorUnits(105_050, 'CDF')->format('fr'))
        ->toContain('050,50')->toContain('CDF');

    // XOF et XAF n'ont pas de subdivision : 105 050 unités mineures =
    // 105 050 francs CFA, sans virgule.
    $xof = Money::fromMinorUnits(105_050, 'XOF')->format('fr');
    expect($xof)->toContain('105')->toContain('050')->not->toContain(',');

    $xaf = Money::fromMinorUnits(105_050, 'XAF')->format('fr');
    expect($xaf)->toContain('105')->toContain('050')->not->toContain(',');
});

it('aucune méthode publique de Money n\'utilise le type float', function (): void {
    $reflection = new ReflectionClass(Money::class);

    foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
        foreach ($method->getParameters() as $parameter) {
            expect((string) $parameter->getType())
                ->not->toContain('float', "Paramètre float détecté : {$method->getName()}(\${$parameter->getName()})");
        }

        expect((string) $method->getReturnType())
            ->not->toContain('float', "Retour float détecté : {$method->getName()}()");
    }
});
