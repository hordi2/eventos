<?php

declare(strict_types=1);

namespace App\Support\Capacity\Models;

enum WaitlistEntryStatus: string
{
    case Waiting = 'waiting';
    case Promoted = 'promoted';
    case Cancelled = 'cancelled';
}
