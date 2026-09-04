<?php

declare(strict_types=1);

namespace App\Domain\CheckIn\Models;

enum BadgeBatchStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';
}
