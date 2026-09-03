<?php

declare(strict_types=1);

namespace App\Domain\Ticketing\Models;

enum TicketStatus: string
{
    case Valid = 'valid';
    case Cancelled = 'cancelled';
}
