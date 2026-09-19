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

    /**
     * $formName : le formulaire par défaut, s'il existe sans être publié.
     */
    public static function missingPublishedForm(?string $formName): self
    {
        return new self($formName === null
            ? "Créez et publiez d'abord le formulaire d'inscription : sans lui, vos invités ne pourraient pas répondre."
            : "Publiez d'abord le formulaire « {$formName} » : c'est lui qu'ouvre le lien de l'événement.");
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
