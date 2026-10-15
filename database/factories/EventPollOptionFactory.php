<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Form\Models\EventPoll;
use App\Domain\Form\Models\EventPollOption;
use App\Domain\Organization\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EventPollOption>
 */
final class EventPollOptionFactory extends Factory
{
    protected $model = EventPollOption::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'event_poll_id' => EventPoll::factory(),
            'label' => fake()->word(),
            'position' => 0,
            'vote_count' => 0,
        ];
    }
}
