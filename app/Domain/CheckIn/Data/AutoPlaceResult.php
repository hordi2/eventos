<?php

declare(strict_types=1);

namespace App\Domain\CheckIn\Data;

final class AutoPlaceResult
{
    /**
     * @param  list<array{guest_type: string, guest_id: int, seating_table_id: int}>  $assignments
     * @param  list<array{guest_type: string, guest_id: int}>  $unplaced
     */
    public function __construct(
        public readonly array $assignments,
        public readonly array $unplaced,
    ) {}
}
