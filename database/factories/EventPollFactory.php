<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Event\Models\Event;
use App\Domain\Form\Models\EventPoll;
use App\Domain\Form\Models\EventPollStatus;
use App\Domain\Organization\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EventPoll>
 */
final class EventPollFactory extends Factory
{
    protected $model = EventPoll::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'event_id' => Event::factory(),
            'question' => fake()->sentence(6).' ?',
            'status' => EventPollStatus::Draft,
        ];
    }

    public function open(): static
    {
        return $this->state(fn (): array => ['status' => EventPollStatus::Open, 'opened_at' => now()]);
    }

    public function closed(): static
    {
        return $this->state(fn (): array => ['status' => EventPollStatus::Closed, 'opened_at' => now()->subHour(), 'closed_at' => now()]);
    }
}
