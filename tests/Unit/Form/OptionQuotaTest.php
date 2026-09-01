<?php

declare(strict_types=1);

use App\Domain\Form\Models\FieldOption;
use App\Domain\Form\Support\OptionQuota;

it('a toujours de la place quand aucun quota n\'est défini', function (): void {
    $option = new FieldOption(['value' => 'poisson', 'label' => 'Poisson', 'position' => 0]);

    expect(app(OptionQuota::class)->hasRemainingCapacity($option, 1000))->toBeTrue();
    expect(app(OptionQuota::class)->remaining($option, 1000))->toBeNull();
});

it('refuse une place au-delà du quota', function (): void {
    $option = new FieldOption(['value' => 'atelier_a', 'label' => 'Atelier A', 'position' => 0, 'quota' => 50]);
    $quota = app(OptionQuota::class);

    expect($quota->hasRemainingCapacity($option, 49))->toBeTrue();
    expect($quota->hasRemainingCapacity($option, 50))->toBeFalse();
});

it('calcule le nombre de places restantes sans jamais descendre sous zéro', function (): void {
    $option = new FieldOption(['value' => 'atelier_a', 'label' => 'Atelier A', 'position' => 0, 'quota' => 50]);
    $quota = app(OptionQuota::class);

    expect($quota->remaining($option, 30))->toBe(20);
    expect($quota->remaining($option, 55))->toBe(0);
});
