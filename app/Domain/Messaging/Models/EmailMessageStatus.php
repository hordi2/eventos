<?php

declare(strict_types=1);

namespace App\Domain\Messaging\Models;

enum EmailMessageStatus: string
{
    case Queued = 'queued';
    case Sent = 'sent';
    case Delivered = 'delivered';
    case Bounced = 'bounced';
    case Complained = 'complained';
    case Failed = 'failed';
}
