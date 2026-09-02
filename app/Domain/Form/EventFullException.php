<?php

declare(strict_types=1);

namespace App\Domain\Form;

use RuntimeException;

final class EventFullException extends RuntimeException
{
    public static function forEvent(int $eventId): self
    {
        return new self("L'événement #{$eventId} a atteint sa capacité maximale et n'a pas de liste d'attente.");
    }
}
