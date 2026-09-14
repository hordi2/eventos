<?php

declare(strict_types=1);

namespace App\Domain\Event;

use RuntimeException;

final class InvalidEventTransitionException extends RuntimeException
{
    public static function cannotPublish(string $currentStatus): self
    {
        return new self("Impossible de publier un événement au statut \"{$currentStatus}\" : seul un brouillon peut être publié.");
    }

    public static function cannotUnpublish(string $currentStatus): self
    {
        return new self("Impossible de dépublier un événement au statut \"{$currentStatus}\" : seul un événement publié peut repasser en brouillon.");
    }

    public static function cannotArchive(string $currentStatus): self
    {
        return new self("Impossible d'archiver un événement déjà au statut \"{$currentStatus}\".");
    }
}
