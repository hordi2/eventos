<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Event\Models\Event;
use App\Domain\Form\Models\AttendeeConnection;
use App\Domain\Form\Models\Registration;
use App\Domain\Organization\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AttendeeConnection>
 */
final class AttendeeConnectionFactory extends Factory
{
    protected $model = AttendeeConnection::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'event_id' => Event::factory(),
            'scanner_registration_id' => Registration::factory(),
            'scanned_registration_id' => Registration::factory(),
        ];
    }
}
