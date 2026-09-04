<?php

declare(strict_types=1);

namespace App\Domain\CheckIn\Data;

final class SeatingTableData
{
    /**
     * @param  list<GuestData>  $guests
     */
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $shape,
        public readonly int $capacity,
        public readonly float $positionX,
        public readonly float $positionY,
        public readonly float $width,
        public readonly float $height,
        public readonly float $rotation,
        public readonly array $guests,
    ) {}
}
