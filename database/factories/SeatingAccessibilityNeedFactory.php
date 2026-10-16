<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\CheckIn\Models\SeatingAccessibilityNeed;
use App\Domain\Organization\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SeatingAccessibilityNeed>
 */
final class SeatingAccessibilityNeedFactory extends Factory
{
    protected $model = SeatingAccessibilityNeed::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'event_id' => fake()->numberBetween(1, 1000),
            'guest_type' => 'attendee',
            'guest_id' => fake()->numberBetween(1, 1000),
        ];
    }
}
