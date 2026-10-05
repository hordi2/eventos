<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Event\Models\Event;
use App\Domain\Form\Models\AttendeeMeeting;
use App\Domain\Form\Models\AttendeeMeetingStatus;
use App\Domain\Form\Models\Registration;
use App\Domain\Organization\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AttendeeMeeting>
 */
final class AttendeeMeetingFactory extends Factory
{
    protected $model = AttendeeMeeting::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'event_id' => Event::factory(),
            'requester_registration_id' => Registration::factory(),
            'guest_registration_id' => Registration::factory(),
            'starts_at' => now()->addDay(),
            'duration_minutes' => 30,
            'place' => 'Hall d’accueil',
            'status' => AttendeeMeetingStatus::Pending,
        ];
    }
}
