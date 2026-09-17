<?php

declare(strict_types=1);

namespace App\Support\Antivirus;

final class ScanResult
{
    private function __construct(
        public readonly bool $isInfected,
        public readonly ?string $signature,
    ) {}

    public static function clean(): self
    {
        return new self(false, null);
    }

    public static function infected(string $signature): self
    {
        return new self(true, $signature);
    }
}
