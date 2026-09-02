<?php

declare(strict_types=1);

namespace App\Domain\Form\Data;

use Carbon\CarbonImmutable;

/**
 * Les seuls attributs d'Event dont UpdateRegistration/CancelRegistration ont
 * besoin, en valeurs simples — même raisonnement que EventRegistrationContext
 * (T-030) : Domain/Form ne référence jamais un modèle de Domain/Event.
 */
final class EventEditPolicy
{
    public function __construct(
        public readonly bool $allowGuestEdit,
        public readonly ?CarbonImmutable $editDeadline,
        public readonly string $timezone,
    ) {}

    public function isLocked(): bool
    {
        if (! $this->allowGuestEdit) {
            return true;
        }

        if ($this->editDeadline === null) {
            return false;
        }

        return CarbonImmutable::now($this->timezone)->greaterThan($this->editDeadline->setTimezone($this->timezone));
    }
}
