<?php

declare(strict_types=1);

namespace App\Domain\Form;

use RuntimeException;

final class SubEventFullException extends RuntimeException
{
    public static function forSession(string $title): self
    {
        return new self("La session « {$title} » est complète : décochez-la pour continuer.");
    }
}
