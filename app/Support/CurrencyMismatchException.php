<?php

declare(strict_types=1);

namespace App\Support;

use RuntimeException;

final class CurrencyMismatchException extends RuntimeException
{
    public function __construct(string $expected, string $actual)
    {
        parent::__construct("Impossible de mélanger les devises {$expected} et {$actual}.");
    }
}
