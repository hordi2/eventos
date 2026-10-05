<?php

declare(strict_types=1);

namespace App\Domain\Form\Models;

/**
 * Où en est un signalement de message (D8).
 */
enum AttendeeReportStatus: string
{
    case Open = 'open';
    case Handled = 'handled';
    case Dismissed = 'dismissed';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'À traiter',
            self::Handled => 'Traité',
            self::Dismissed => 'Classé sans suite',
        };
    }
}
