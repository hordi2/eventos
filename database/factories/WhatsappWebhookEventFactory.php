<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Messaging\Models\WhatsappWebhookEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WhatsappWebhookEvent>
 */
final class WhatsappWebhookEventFactory extends Factory
{
    protected $model = WhatsappWebhookEvent::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'provider' => 'twilio',
            'event_id' => (string) fake()->uuid(),
            'payload' => ['MessageStatus' => 'delivered'],
            'processed_at' => now(),
        ];
    }
}
