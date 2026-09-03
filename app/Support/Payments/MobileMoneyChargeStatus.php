<?php

declare(strict_types=1);

namespace App\Support\Payments;

enum MobileMoneyChargeStatus: string
{
    case Pending = 'pending';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
}
