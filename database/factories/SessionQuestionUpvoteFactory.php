<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Form\Models\Registration;
use App\Domain\Form\Models\SessionQuestion;
use App\Domain\Form\Models\SessionQuestionUpvote;
use App\Domain\Organization\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SessionQuestionUpvote>
 */
final class SessionQuestionUpvoteFactory extends Factory
{
    protected $model = SessionQuestionUpvote::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'session_question_id' => SessionQuestion::factory(),
            'registration_id' => Registration::factory(),
            'created_at' => now(),
        ];
    }
}
