<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Event\Models\Event;
use App\Domain\Form\Models\Registration;
use App\Domain\Messaging\Models\PushSubscription;
use App\Domain\Organization\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PushSubscription>
 */
final class PushSubscriptionFactory extends Factory
{
    protected $model = PushSubscription::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'event_id' => Event::factory(),
            'registration_id' => Registration::factory(),
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/'.fake()->unique()->uuid(),
        ];
    }
}
