<?php

declare(strict_types=1);

namespace App\Domain\Ticketing\Models;

enum PaymentStatus: string
{
    case Pending = 'pending';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
}
