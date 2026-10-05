<?php

declare(strict_types=1);

namespace App\Support\Sustainability;

/**
 * L'empreinte carbone d'un événement, telle qu'elle s'affiche et s'imprime
 * (D12). Tous les poids sont en kilogrammes d'équivalent CO₂.
 */
final class CarbonFootprintData
{
    /**
     * @param  list<array{mode: string, label: string, people: int, kilometres: int, kilograms: float}>  $travelByMode
     */
    public function __construct(
        public readonly float $travelKilograms,
        public readonly float $mealKilograms,
        public readonly float $printKilograms,
        public readonly float $totalKilograms,
        public readonly int $attendeeCount,
        public readonly int $declaredCount,
        public readonly int $mealsServed,
        public readonly int $printedPages,
        public readonly array $travelByMode,
        public readonly int $carpoolOffers,
        public readonly int $carpoolSeekers,
    ) {}

    /**
     * Par participant : c'est ce chiffre qu'un appel d'offres demande, plus
     * que le total.
     */
    public function kilogramsPerAttendee(): float
    {
        return $this->attendeeCount === 0 ? 0.0 : round($this->totalKilograms / $this->attendeeCount, 1);
    }

    /**
     * Part des participants qui ont déclaré leur déplacement : sans elle, le
     * total ne veut rien dire.
     */
    public function declarationRate(): int
    {
        return $this->attendeeCount === 0 ? 0 : (int) round($this->declaredCount / $this->attendeeCount * 100);
    }
}
