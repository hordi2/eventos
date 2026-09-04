<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\CheckIn\Models\SeatingConstraint;
use App\Domain\CheckIn\Models\SeatingConstraintType;
use App\Domain\Organization\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SeatingConstraint>
 */
final class SeatingConstraintFactory extends Factory
{
    protected $model = SeatingConstraint::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'event_id' => fake()->numberBetween(1, 1000),
            'guest_a_type' => 'attendee',
            'guest_a_id' => fake()->numberBetween(1, 1000),
            'guest_b_type' => 'attendee',
            'guest_b_id' => fake()->numberBetween(1, 1000),
            'type' => SeatingConstraintType::MustNotBeWith,
        ];
    }
}
