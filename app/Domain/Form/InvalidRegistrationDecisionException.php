<?php

declare(strict_types=1);

namespace App\Domain\Form;

use RuntimeException;

final class InvalidRegistrationDecisionException extends RuntimeException
{
    public static function notPending(string $currentStatus): self
    {
        return new self("Cette inscription n'attend plus votre validation : elle est « {$currentStatus} ».");
    }
}
