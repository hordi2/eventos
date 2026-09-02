<?php

declare(strict_types=1);

namespace App\Domain\Contact\Models;

enum ContactImportStatus: string
{
    case Mapping = 'mapping';
    case Queued = 'queued';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';
}
