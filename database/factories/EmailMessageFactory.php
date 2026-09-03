<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Messaging\Models\EmailMessage;
use App\Domain\Messaging\Models\EmailMessageStatus;
use App\Domain\Organization\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmailMessage>
 */
final class EmailMessageFactory extends Factory
{
    protected $model = EmailMessage::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'to_email' => fake()->unique()->safeEmail(),
            'subject' => fake()->sentence(4),
            'is_transactional' => true,
            'status' => EmailMessageStatus::Queued,
            'provider' => 'postmark',
        ];
    }

    public function sent(): static
    {
        return $this->state(fn (): array => [
            'status' => EmailMessageStatus::Sent,
            'provider_message_id' => (string) fake()->uuid(),
            'sent_at' => now(),
        ]);
    }
}
