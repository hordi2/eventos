<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Event\Models\Event;
use App\Domain\Form\Models\AttendeeBlock;
use App\Domain\Form\Models\Registration;
use App\Domain\Organization\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AttendeeBlock>
 */
final class AttendeeBlockFactory extends Factory
{
    protected $model = AttendeeBlock::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'event_id' => Event::factory(),
            'blocker_registration_id' => Registration::factory(),
            'blocked_registration_id' => Registration::factory(),
        ];
    }
}
