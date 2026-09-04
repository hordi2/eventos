<?php

declare(strict_types=1);

namespace App\Domain\CheckIn;

use RuntimeException;

final class SeatingTableFullException extends RuntimeException
{
    public static function forTable(int $tableId): self
    {
        return new self("La table #{$tableId} a atteint sa capacité maximale.");
    }
}
