<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\CheckIn\Models\SeatAssignment;
use App\Domain\Organization\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SeatAssignment>
 */
final class SeatAssignmentFactory extends Factory
{
    protected $model = SeatAssignment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'event_id' => fake()->numberBetween(1, 1000),
            'seating_table_id' => SeatingTableFactory::new(),
            'guest_type' => 'attendee',
            'guest_id' => fake()->numberBetween(1, 1000),
        ];
    }
}
