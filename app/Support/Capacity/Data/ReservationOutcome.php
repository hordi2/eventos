<?php

declare(strict_types=1);

namespace App\Support\Capacity\Data;

enum ReservationOutcome: string
{
    case Accepted = 'accepted';
    case Waitlisted = 'waitlisted';
    case Rejected = 'rejected';
}
