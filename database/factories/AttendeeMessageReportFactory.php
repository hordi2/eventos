<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Event\Models\Event;
use App\Domain\Form\Models\AttendeeMessage;
use App\Domain\Form\Models\AttendeeMessageReport;
use App\Domain\Form\Models\AttendeeReportStatus;
use App\Domain\Form\Models\Registration;
use App\Domain\Organization\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AttendeeMessageReport>
 */
final class AttendeeMessageReportFactory extends Factory
{
    protected $model = AttendeeMessageReport::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'event_id' => Event::factory(),
            'attendee_message_id' => AttendeeMessage::factory(),
            'reporter_registration_id' => Registration::factory(),
            'reason' => 'Propos déplacés.',
            'status' => AttendeeReportStatus::Open,
        ];
    }
}
