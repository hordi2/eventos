<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Messaging\Models\EmailWebhookEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmailWebhookEvent>
 */
final class EmailWebhookEventFactory extends Factory
{
    protected $model = EmailWebhookEvent::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'provider' => 'postmark',
            'event_id' => (string) fake()->uuid(),
            'payload' => ['RecordType' => 'Delivery'],
            'processed_at' => now(),
        ];
    }
}
