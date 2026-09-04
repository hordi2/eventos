<?php

declare(strict_types=1);

namespace App\Domain\CheckIn\Models;

enum CheckInDirection: string
{
    case CheckIn = 'check_in';
    case CheckOut = 'check_out';
}
