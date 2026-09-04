<?php

declare(strict_types=1);

namespace App\Domain\CheckIn\Data;

final class GuestData
{
    public function __construct(
        public readonly string $guestType,
        public readonly int $id,
        public readonly string $name,
        public readonly ?string $email,
        public readonly ?string $phone,
        public readonly bool $checkedIn,
        public readonly ?string $checkedInAt,
    ) {}
}
