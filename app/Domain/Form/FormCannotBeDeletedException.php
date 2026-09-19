<?php

declare(strict_types=1);

namespace App\Domain\Form;

use RuntimeException;

final class FormCannotBeDeletedException extends RuntimeException
{
    public static function isDefault(): self
    {
        return new self("Ce formulaire répond au lien de l'événement : choisissez d'abord un autre formulaire par défaut.");
    }

    public static function hasResponses(): self
    {
        return new self('Ce formulaire a déjà reçu des réponses : il est conservé pour pouvoir les lire.');
    }
}
