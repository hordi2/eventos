<?php

declare(strict_types=1);

namespace App\Domain\Messaging\Models;

enum WhatsappMessageStatus: string
{
    case Queued = 'queued';
    case Sent = 'sent';
    case Delivered = 'delivered';
    case Read = 'read';
    case Failed = 'failed';
    case Undelivered = 'undelivered';
}
