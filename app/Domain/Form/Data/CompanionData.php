<?php

declare(strict_types=1);

namespace App\Domain\Form\Data;

/**
 * Une personne qui accompagne le titulaire d'une inscription (T-032).
 * attendeeId n'est connu qu'en modification, pour retrouver le participant
 * déjà enregistré.
 */
final class CompanionData
{
    /**
     * Clé des accompagnants dans les données du parcours invité. Un champ de
     * formulaire ne peut pas porter cette clé (SaveFormRequest).
     */
    public const INPUT_KEY = '_companions';

    /**
     * @param  array<string, mixed>  $answers  réponses aux questions posées « à chaque personne »
     */
    public function __construct(
        public readonly string $firstName,
        public readonly ?string $lastName = null,
        public readonly array $answers = [],
        public readonly ?int $attendeeId = null,
    ) {}
}
