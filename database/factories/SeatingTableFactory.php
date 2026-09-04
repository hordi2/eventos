<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\CheckIn\Models\SeatingTable;
use App\Domain\CheckIn\Models\SeatingTableShape;
use App\Domain\Organization\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SeatingTable>
 */
final class SeatingTableFactory extends Factory
{
    protected $model = SeatingTable::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'event_id' => fake()->numberBetween(1, 1000),
            'name' => 'Table '.fake()->numberBetween(1, 30),
            'shape' => SeatingTableShape::Round,
            'capacity' => 8,
            'position_x' => fake()->numberBetween(0, 800),
            'position_y' => fake()->numberBetween(0, 600),
            'width' => 120,
            'height' => 120,
            'rotation' => 0,
        ];
    }
}
