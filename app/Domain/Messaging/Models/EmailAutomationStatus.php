<?php

declare(strict_types=1);

namespace App\Domain\Messaging\Models;

enum EmailAutomationStatus: string
{
    // Règle permanente pour "confirmation" : jamais "sent" ni "scheduled",
    // reste "active" jusqu'à annulation — chaque inscription confirmée la
    // déclenche à nouveau (voir App\Listeners\SendConfirmationEmail).
    case Active = 'active';
    case Scheduled = 'scheduled';
    case Sent = 'sent';
    case Cancelled = 'cancelled';
}
