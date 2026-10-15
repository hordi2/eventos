<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Event\Models\Event;
use App\Domain\Messaging\Models\PushNotification;
use App\Domain\Organization\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PushNotification>
 */
final class PushNotificationFactory extends Factory
{
    protected $model = PushNotification::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'event_id' => Event::factory(),
            'title' => fake()->sentence(3),
            'body' => fake()->sentence(10),
            'recipient_count' => 0,
            'sent_at' => null,
        ];
    }

    public function sent(): static
    {
        return $this->state(fn (): array => [
            'recipient_count' => fake()->numberBetween(1, 200),
            'sent_at' => now(),
        ]);
    }
}
