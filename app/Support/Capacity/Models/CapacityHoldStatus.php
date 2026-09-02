<?php

declare(strict_types=1);

namespace App\Support\Capacity\Models;

enum CapacityHoldStatus: string
{
    case Held = 'held';
    case Released = 'released';
}
