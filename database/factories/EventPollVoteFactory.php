<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Form\Models\EventPoll;
use App\Domain\Form\Models\EventPollOption;
use App\Domain\Form\Models\EventPollVote;
use App\Domain\Form\Models\Registration;
use App\Domain\Organization\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EventPollVote>
 */
final class EventPollVoteFactory extends Factory
{
    protected $model = EventPollVote::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'event_poll_id' => EventPoll::factory(),
            'event_poll_option_id' => EventPollOption::factory(),
            'registration_id' => Registration::factory(),
        ];
    }
}
