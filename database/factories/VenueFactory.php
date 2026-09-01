<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Event\Models\Venue;
use App\Domain\Organization\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Venue>
 */
final class VenueFactory extends Factory
{
    protected $model = Venue::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'name' => fake()->company(),
            'address' => fake()->address(),
        ];
    }

    public function withCoordinates(): static
    {
        return $this->state(fn (): array => [
            'latitude' => fake()->latitude(),
            'longitude' => fake()->longitude(),
        ]);
    }
}
