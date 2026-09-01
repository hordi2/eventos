<?php

declare(strict_types=1);

namespace App\Domain\Form;

use RuntimeException;

final class InvalidFormVersionTransitionException extends RuntimeException
{
    public static function cannotEditPublishedDraft(): self
    {
        return new self('Impossible de modifier en place une version déjà publiée ou archivée : crée une nouvelle version avec ReviseForm.');
    }

    public static function cannotPublish(string $currentStatus): self
    {
        return new self("Impossible de publier une version au statut \"{$currentStatus}\" : seul un brouillon peut être publié.");
    }

    public static function cannotReviseFromDraft(): self
    {
        return new self("Impossible de créer une nouvelle version tant que la dernière n'est pas publiée : utilise UpdateFormDraft pour continuer à modifier le brouillon actuel.");
    }
}
