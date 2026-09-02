<?php

declare(strict_types=1);

namespace App\Domain\Form;

use RuntimeException;

final class OptionFullException extends RuntimeException
{
    public static function forOption(string $fieldLabel, string $optionLabel): self
    {
        return new self("L'option \"{$optionLabel}\" du champ \"{$fieldLabel}\" est complète.");
    }
}
