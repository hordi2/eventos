<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Event\Models\Event;
use App\Domain\Event\Models\Speaker;
use App\Domain\Organization\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Speaker>
 */
final class SpeakerFactory extends Factory
{
    protected $model = Speaker::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'event_id' => Event::factory(),
            'name' => fake()->name(),
            'role' => 'Directrice des opérations',
            'company' => fake()->company(),
            'bio' => fake()->paragraph(),
            'position' => 0,
        ];
    }
}
