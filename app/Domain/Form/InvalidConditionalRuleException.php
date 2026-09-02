<?php

declare(strict_types=1);

namespace App\Domain\Form;

use RuntimeException;

final class InvalidConditionalRuleException extends RuntimeException
{
    public static function unknownField(string $fieldKey): self
    {
        return new self("Le champ \"{$fieldKey}\" n'existe pas dans cette version du formulaire.");
    }

    public static function duplicateTarget(string $fieldKey): self
    {
        return new self("Le champ \"{$fieldKey}\" est déjà la cible d'une autre règle : une seule règle par champ est autorisée.");
    }

    public static function circularDependency(): self
    {
        return new self('Boucle détectée dans les règles de logique conditionnelle : au moins un champ dépend finalement de lui-même.');
    }
}
