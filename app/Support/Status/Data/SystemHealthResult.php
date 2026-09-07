<?php

declare(strict_types=1);

namespace App\Support\Status\Data;

final readonly class SystemHealthResult
{
    /**
     * @param  array<string, bool>  $components
     */
    public function __construct(
        public array $components,
    ) {}

    public function isHealthy(): bool
    {
        return ! in_array(false, $this->components, strict: true);
    }
}
