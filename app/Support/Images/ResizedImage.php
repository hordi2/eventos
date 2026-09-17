<?php

declare(strict_types=1);

namespace App\Support\Images;

final class ResizedImage
{
    public function __construct(
        public readonly string $content,
        public readonly string $extension,
        public readonly int $width,
        public readonly int $height,
    ) {}
}
