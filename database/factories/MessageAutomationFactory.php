<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Messaging\Models\EmailTemplate;
use App\Domain\Messaging\Models\MessageAutomation;
use App\Domain\Messaging\Models\MessageAutomationStatus;
use App\Domain\Messaging\Models\MessageAutomationType;
use App\Domain\Messaging\Models\MessageChannel;
use App\Domain\Organization\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MessageAutomation>
 */
final class MessageAutomationFactory extends Factory
{
    protected $model = MessageAutomation::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'channel' => MessageChannel::Email,
            'email_template_id' => EmailTemplate::factory(),
            'created_by' => User::factory(),
            'type' => MessageAutomationType::ReminderUnanswered,
            'segment' => null,
            'scheduled_at' => now()->addDay(),
            'status' => MessageAutomationStatus::Scheduled,
        ];
    }

    public function whatsapp(): static
    {
        return $this->state(fn (): array => [
            'channel' => MessageChannel::Whatsapp,
            'email_template_id' => null,
            'whatsapp_template_id' => WhatsappTemplateFactory::new(),
        ]);
    }
}
