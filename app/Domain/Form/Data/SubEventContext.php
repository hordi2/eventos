<?php

declare(strict_types=1);

namespace App\Domain\Form\Data;

use Carbon\CarbonImmutable;

/**
 * Un événement secondaire proposé à l'inscription, déjà résolu en valeurs
 * simples par l'appelant : Domain/Form ne référence jamais un modèle de
 * Domain/Event (section 3 du CLAUDE.md).
 */
final class SubEventContext
{
    public function __construct(
        public readonly int $eventId,
        public readonly string $title,
        public readonly ?int $capacity,
        public readonly bool $allowWaitlist,
        public readonly CarbonImmutable $startAt,
        public readonly CarbonImmutable $endAt,
    ) {}

    public function overlaps(self $other): bool
    {
        return $this->startAt->lessThan($other->endAt) && $other->startAt->lessThan($this->endAt);
    }
}
