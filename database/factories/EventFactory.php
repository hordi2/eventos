<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Event\Models\Event;
use App\Domain\Event\Models\EventAccessMode;
use App\Domain\Event\Models\EventStatus;
use App\Domain\Event\Models\EventType;
use App\Domain\Organization\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Event>
 */
final class EventFactory extends Factory
{
    protected $model = Event::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startAt = fake()->dateTimeBetween('+1 week', '+2 months');
        $endAt = (clone $startAt)->modify('+3 hours');

        return [
            'organization_id' => Organization::factory(),
            'created_by' => User::factory(),
            'title' => fake()->sentence(4),
            'type' => fake()->randomElement(EventType::cases()),
            'status' => EventStatus::Draft,
            'slug' => fake()->unique()->slug(),
            'start_at' => $startAt,
            'end_at' => $endAt,
            'timezone' => 'Africa/Kinshasa',
            'access_mode' => EventAccessMode::Public,
        ];
    }

    public function published(): static
    {
        return $this->state(fn (): array => ['status' => EventStatus::Published]);
    }

    public function archived(): static
    {
        return $this->state(fn (): array => ['status' => EventStatus::Archived]);
    }

    public function subEventOf(Event $parent): static
    {
        return $this->state(fn (): array => [
            'organization_id' => $parent->organization_id,
            'parent_event_id' => $parent->id,
        ]);
    }
}
