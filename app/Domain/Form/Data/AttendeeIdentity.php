<?php

declare(strict_types=1);

namespace App\Domain\Form\Data;

/**
 * En attendant Contact (T-040), l'identité de l'invité est un bloc fixe,
 * indépendant des champs dynamiques que l'organisateur configure dans le
 * formulaire — décision prise pour ce ticket (T-030).
 */
final class AttendeeIdentity
{
    public function __construct(
        public readonly string $email,
        public readonly ?string $firstName = null,
        public readonly ?string $lastName = null,
        public readonly ?string $phone = null,
    ) {}
}
