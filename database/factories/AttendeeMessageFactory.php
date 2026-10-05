<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Event\Models\Event;
use App\Domain\Form\Models\AttendeeMessage;
use App\Domain\Form\Models\Registration;
use App\Domain\Organization\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AttendeeMessage>
 */
final class AttendeeMessageFactory extends Factory
{
    protected $model = AttendeeMessage::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'event_id' => Event::factory(),
            'from_registration_id' => Registration::factory(),
            'to_registration_id' => Registration::factory(),
            'body' => 'Ravi de vous avoir rencontré.',
        ];
    }
}
