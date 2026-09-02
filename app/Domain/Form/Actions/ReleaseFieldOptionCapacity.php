<?php

declare(strict_types=1);

namespace App\Domain\Form\Actions;

use App\Domain\Form\Models\FieldOption;
use App\Support\Capacity\Actions\ReleaseCapacity;

final class ReleaseFieldOptionCapacity
{
    public function __construct(private readonly ReleaseCapacity $releaseCapacity) {}

    public function handle(FieldOption $option, string $reservationKey): void
    {
        $this->releaseCapacity->handle('form_field_option', (string) $option->id, $reservationKey);
    }
}
