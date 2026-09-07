<?php

declare(strict_types=1);

namespace App\Domain\Organization\Models;

enum ThemeMode: string
{
    case Light = 'light';
    case Dark = 'dark';

    public function label(): string
    {
        return match ($this) {
            self::Light => 'Normal',
            self::Dark => 'Sombre',
        };
    }
}
