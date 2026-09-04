<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\CheckIn\Models\BadgeBatch;
use App\Domain\CheckIn\Models\BadgeBatchStatus;
use App\Domain\Organization\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BadgeBatch>
 */
final class BadgeBatchFactory extends Factory
{
    protected $model = BadgeBatch::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'event_id' => fake()->numberBetween(1, 1000),
            'status' => BadgeBatchStatus::Pending,
            'guest_count' => null,
            'file_path' => null,
            'created_by' => User::factory(),
            'completed_at' => null,
        ];
    }

    public function completed(): self
    {
        return $this->state(fn (): array => [
            'status' => BadgeBatchStatus::Completed,
            'guest_count' => 2,
            'file_path' => 'badges/demo.pdf',
            'completed_at' => now(),
        ]);
    }
}
