<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Organization\Models\Organization;
use App\Support\Capacity\Models\CapacityHold;
use App\Support\Capacity\Models\CapacityHoldStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CapacityHold>
 */
final class CapacityHoldFactory extends Factory
{
    protected $model = CapacityHold::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'holder_type' => 'event',
            'holder_id' => (string) fake()->randomNumber(5),
            'reservation_key' => fake()->unique()->uuid(),
            'quantity' => 1,
            'status' => CapacityHoldStatus::Held,
        ];
    }

    public function released(): static
    {
        return $this->state(fn (): array => ['status' => CapacityHoldStatus::Released, 'released_at' => now()]);
    }
}
