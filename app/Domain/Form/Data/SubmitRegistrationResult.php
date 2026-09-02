<?php

declare(strict_types=1);

namespace App\Domain\Form\Data;

use App\Domain\Form\Models\Registration;

final class SubmitRegistrationResult
{
    private function __construct(
        public readonly SubmitRegistrationOutcome $outcome,
        public readonly Registration $registration,
    ) {}

    /**
     * Inscription créée — soit à l'instant, soit rejouée de façon idempotente
     * depuis une clé de réservation déjà traitée.
     */
    public static function created(Registration $registration): self
    {
        return new self(SubmitRegistrationOutcome::Created, $registration);
    }

    /**
     * Un doublon par e-mail a été détecté : rien n'a été créé, $registration
     * porte l'inscription existante — à l'appelant de proposer à l'invité de
     * la modifier plutôt que d'en créer une nouvelle (M2.4 du CDC).
     */
    public static function duplicateFound(Registration $registration): self
    {
        return new self(SubmitRegistrationOutcome::DuplicateFound, $registration);
    }
}
