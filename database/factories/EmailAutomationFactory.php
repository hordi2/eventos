<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Messaging\Models\EmailAutomation;
use App\Domain\Messaging\Models\EmailAutomationStatus;
use App\Domain\Messaging\Models\EmailAutomationType;
use App\Domain\Messaging\Models\EmailTemplate;
use App\Domain\Organization\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmailAutomation>
 */
final class EmailAutomationFactory extends Factory
{
    protected $model = EmailAutomation::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'email_template_id' => EmailTemplate::factory(),
            'created_by' => User::factory(),
            'type' => EmailAutomationType::ReminderUnanswered,
            'segment' => null,
            'scheduled_at' => now()->addDay(),
            'status' => EmailAutomationStatus::Scheduled,
        ];
    }
}
