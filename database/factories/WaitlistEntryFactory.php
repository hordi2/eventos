<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Organization\Models\Organization;
use App\Support\Capacity\Models\WaitlistEntry;
use App\Support\Capacity\Models\WaitlistEntryStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WaitlistEntry>
 */
final class WaitlistEntryFactory extends Factory
{
    protected $model = WaitlistEntry::class;

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
            'position' => 1,
            'status' => WaitlistEntryStatus::Waiting,
        ];
    }

    public function promoted(): static
    {
        return $this->state(fn (): array => ['status' => WaitlistEntryStatus::Promoted, 'promoted_at' => now()]);
    }
}
