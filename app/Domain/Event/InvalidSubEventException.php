<?php

declare(strict_types=1);

namespace App\Domain\Event;

use RuntimeException;

final class InvalidSubEventException extends RuntimeException
{
    public static function nestedSubEvent(): self
    {
        return new self('Un sous-événement ne peut pas lui-même avoir de sous-événements (un seul niveau de hiérarchie est autorisé).');
    }
}
