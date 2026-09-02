<?php

declare(strict_types=1);

namespace App\Domain\Form\Data;

use Carbon\CarbonImmutable;

/**
 * Les seuls attributs d'Event dont SubmitRegistration a besoin, portés en
 * valeurs simples plutôt qu'un modèle Event : Domain/Form ne référence
 * jamais directement un modèle de Domain/Event (section 3 du CLAUDE.md).
 * L'appelant (futur contrôleur invité de T-031, ou un test) charge l'Event
 * et construit ce contexte à partir de ses attributs.
 */
final class EventRegistrationContext
{
    public function __construct(
        public readonly int $eventId,
        public readonly int $organizationId,
        public readonly ?int $capacity,
        public readonly bool $allowWaitlist,
        public readonly ?CarbonImmutable $registrationOpensAt,
        public readonly ?CarbonImmutable $registrationClosesAt,
        public readonly string $timezone,
        public readonly ?string $registrationClosedMessage,
    ) {}
}
