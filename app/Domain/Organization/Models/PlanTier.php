<?php

declare(strict_types=1);

namespace App\Domain\Organization\Models;

enum PlanTier: string
{
    case Free = 'free';
    case Pro = 'pro';
    case Business = 'business';

    public function label(): string
    {
        return match ($this) {
            self::Free => 'Gratuit',
            self::Pro => 'Pro',
            self::Business => 'Business',
        };
    }
}
