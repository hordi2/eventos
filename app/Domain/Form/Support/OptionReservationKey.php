<?php

declare(strict_types=1);

namespace App\Domain\Form\Support;

/**
 * Clé de réservation du quota d'une option. Le titulaire garde la clé
 * historique ; chaque accompagnant a la sienne, pour qu'un même plat choisi
 * par trois personnes tienne bien trois places.
 */
final class OptionReservationKey
{
    public static function for(string $registrationKey, int $optionId, ?int $attendeeId = null): string
    {
        return $attendeeId === null
            ? "{$registrationKey}:option:{$optionId}"
            : "{$registrationKey}:attendee:{$attendeeId}:option:{$optionId}";
    }
}
