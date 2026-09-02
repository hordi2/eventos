<?php

declare(strict_types=1);

namespace App\Domain\Form;

use RuntimeException;

final class RegistrationClosedException extends RuntimeException
{
    private const DEFAULT_MESSAGE = 'Les inscriptions ne sont pas ouvertes pour le moment.';

    public static function outsideWindow(?string $customMessage): self
    {
        return new self($customMessage ?? self::DEFAULT_MESSAGE);
    }
}
