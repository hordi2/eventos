<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\CheckIn\Models\CheckIn;
use App\Domain\CheckIn\Models\CheckInDirection;
use App\Domain\CheckIn\Models\CheckInStatus;
use App\Domain\Organization\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CheckIn>
 */
final class CheckInFactory extends Factory
{
    protected $model = CheckIn::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'event_id' => fake()->numberBetween(1, 1000),
            'attendee_id' => fake()->numberBetween(1, 1000),
            'ticket_id' => null,
            'device_local_id' => (string) Str::uuid(),
            'direction' => CheckInDirection::CheckIn,
            'status' => CheckInStatus::Accepted,
            'recorded_at' => now(),
            'checked_in_by' => null,
        ];
    }

    public function forTicket(int $ticketId): self
    {
        return $this->state(fn (): array => ['attendee_id' => null, 'ticket_id' => $ticketId]);
    }

    public function conflict(): self
    {
        return $this->state(fn (): array => ['status' => CheckInStatus::Conflict]);
    }
}
