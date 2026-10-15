<?php

declare(strict_types=1);

namespace App\Domain\Form\Models;

/**
 * Où en est un sondage en direct (D9).
 */
enum EventPollStatus: string
{
    case Draft = 'draft';
    case Open = 'open';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Brouillon',
            self::Open => 'Ouvert',
            self::Closed => 'Fermé',
        };
    }
}
