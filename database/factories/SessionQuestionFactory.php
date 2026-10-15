<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Event\Models\Event;
use App\Domain\Form\Models\Registration;
use App\Domain\Form\Models\SessionQuestion;
use App\Domain\Form\Models\SessionQuestionStatus;
use App\Domain\Organization\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SessionQuestion>
 */
final class SessionQuestionFactory extends Factory
{
    protected $model = SessionQuestion::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'event_id' => Event::factory(),
            'registration_id' => Registration::factory(),
            'body' => fake()->sentence(10).' ?',
            'upvote_count' => 0,
            'status' => SessionQuestionStatus::Pending,
        ];
    }

    public function answered(): static
    {
        return $this->state(fn (): array => [
            'status' => SessionQuestionStatus::Answered,
            'answer' => fake()->sentence(8),
            'answered_at' => now(),
        ]);
    }

    public function hidden(): static
    {
        return $this->state(fn (): array => ['status' => SessionQuestionStatus::Hidden]);
    }
}
