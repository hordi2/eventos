<?php

declare(strict_types=1);

namespace App\Domain\CheckIn\Models;

enum SeatingConstraintType: string
{
    case MustBeWith = 'must_be_with';
    case MustNotBeWith = 'must_not_be_with';
}
