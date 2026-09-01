<?php

declare(strict_types=1);

namespace App\Domain\Form;

use RuntimeException;

final class InvalidFieldAnswerException extends RuntimeException
{
    public static function forField(string $label, string $reason): self
    {
        return new self("Réponse invalide pour le champ \"{$label}\" : {$reason}");
    }
}
