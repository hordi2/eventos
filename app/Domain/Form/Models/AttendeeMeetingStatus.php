<?php

declare(strict_types=1);

namespace App\Domain\Form\Models;

/**
 * Où en est un rendez-vous entre participants (D8).
 */
enum AttendeeMeetingStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Declined = 'declined';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'En attente de réponse',
            self::Accepted => 'Accepté',
            self::Declined => 'Décliné',
            self::Cancelled => 'Annulé',
        };
    }
}
