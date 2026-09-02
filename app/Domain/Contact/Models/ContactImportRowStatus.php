<?php

declare(strict_types=1);

namespace App\Domain\Contact\Models;

enum ContactImportRowStatus: string
{
    case Accepted = 'accepted';
    case Merged = 'merged';
    case Skipped = 'skipped';
    case Rejected = 'rejected';
}
