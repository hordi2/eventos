<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Messaging\Models\WhatsappMessage;
use App\Domain\Messaging\Models\WhatsappMessageStatus;
use App\Domain\Organization\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WhatsappMessage>
 */
final class WhatsappMessageFactory extends Factory
{
    protected $model = WhatsappMessage::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'to_phone_e164' => '+243'.fake()->numerify('#########'),
            'status' => WhatsappMessageStatus::Queued,
            'provider' => 'twilio',
        ];
    }

    public function sent(): static
    {
        return $this->state(fn (): array => [
            'status' => WhatsappMessageStatus::Sent,
            'provider_message_id' => 'SM'.fake()->regexify('[0-9a-f]{32}'),
            'sent_at' => now(),
        ]);
    }
}
