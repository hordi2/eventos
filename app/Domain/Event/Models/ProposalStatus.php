<?php

declare(strict_types=1);

namespace App\Domain\Event\Models;

/**
 * Où en est un sujet proposé (D6) : tant qu'il n'est pas tranché, il
 * attend une évaluation.
 */
enum ProposalStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'À évaluer',
            self::Accepted => 'Retenu',
            self::Rejected => 'Refusé',
        };
    }
}
