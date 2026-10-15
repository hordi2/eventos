<?php

declare(strict_types=1);

namespace App\Domain\Form\Models;

/**
 * Où en est une question posée au conférencier (D9).
 */
enum SessionQuestionStatus: string
{
    case Pending = 'pending';
    case Answered = 'answered';
    case Hidden = 'hidden';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'En attente',
            self::Answered => 'Répondue',
            self::Hidden => 'Masquée',
        };
    }
}
