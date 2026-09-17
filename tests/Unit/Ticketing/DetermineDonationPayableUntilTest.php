<?php

declare(strict_types=1);

use App\Domain\Ticketing\Actions\DetermineDonationPayableUntil;
use Carbon\CarbonImmutable;

beforeEach(function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-09-17 10:00:00', 'UTC'));
});

it('laisse régler un don jusqu\'à deux jours après la fin de l\'événement', function (): void {
    $payableUntil = (new DetermineDonationPayableUntil)->handle(CarbonImmutable::parse('2026-10-01 18:00:00', 'UTC'));

    expect($payableUntil->toIso8601String())->toBe('2026-10-03T18:00:00+00:00');
});

it('laisse deux jours à partir de maintenant quand l\'événement est déjà terminé', function (): void {
    $payableUntil = (new DetermineDonationPayableUntil)->handle(CarbonImmutable::parse('2026-09-01 18:00:00', 'UTC'));

    expect($payableUntil->toIso8601String())->toBe('2026-09-19T10:00:00+00:00');
});
