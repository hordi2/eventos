<?php

declare(strict_types=1);

namespace App\Domain\Event\Events;

use App\Domain\Event\Data\EventDuplicationPart;
use App\Domain\Organization\Models\Organization;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Émis par DuplicateEvent, dans sa transaction, une fois l'événement et ses
 * sessions recopiés : chaque module reprend alors sa partie (formulaires,
 * page, billets, liste d'invités, messages) sans jamais lire les modèles de
 * Domain/Event. Écouteurs synchrones : un échec annule toute la copie.
 */
final class EventDuplicated
{
    use Dispatchable;

    /**
     * @param  array<int, int>  $eventIdMap  événement ou session d'origine => sa copie
     * @param  list<EventDuplicationPart>  $parts
     */
    public function __construct(
        public readonly Organization $organization,
        public readonly User $duplicator,
        public readonly array $eventIdMap,
        public readonly int $offsetSeconds,
        public readonly array $parts,
    ) {}

    public function includes(EventDuplicationPart $part): bool
    {
        return in_array($part, $this->parts, true);
    }

    /**
     * Toute date de l'original, décalée du même écart que l'événement.
     */
    public function shift(?CarbonImmutable $date): ?CarbonImmutable
    {
        return $date?->addSeconds($this->offsetSeconds);
    }
}
