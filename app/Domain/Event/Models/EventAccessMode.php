<?php

declare(strict_types=1);

namespace App\Domain\Event\Models;

enum EventAccessMode: string
{
    case Public = 'public';
    case PrivateLink = 'private_link';
    case ClosedList = 'closed_list';
    case Password = 'password';
}
