<?php

declare(strict_types=1);

namespace App\Domain\Event;

use RuntimeException;

final class CannotDeleteEventException extends RuntimeException
{
    public static function hasSubEvents(): self
    {
        return new self("Impossible de supprimer un événement qui a encore des sous-événements : supprime-les d'abord.");
    }

    public static function hasRegistrations(): self
    {
        return new self('Des invités sont inscrits à cette session : annulez leurs inscriptions avant de la supprimer.');
    }
}
