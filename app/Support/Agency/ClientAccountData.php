<?php

declare(strict_types=1);

namespace App\Support\Agency;

use App\Support\Money;

/**
 * Un compte client vu depuis le portail de son agence (D10).
 */
final class ClientAccountData
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $slug,
        public readonly int $eventCount,
        public readonly ?string $nextEventTitle,
        public readonly ?string $nextEventDate,
        public readonly int $registrationCount,
        public readonly Money $revenue,
        public readonly ?string $managedSince,
    ) {}
}
