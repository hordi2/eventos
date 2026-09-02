<?php

declare(strict_types=1);

namespace App\Domain\Form;

use RuntimeException;

final class RegistrationEditLockedException extends RuntimeException
{
    public static function locked(): self
    {
        return new self("La modification de cette inscription n'est plus possible.");
    }
}
