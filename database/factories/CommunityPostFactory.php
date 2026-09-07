<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Community\Models\CommunityPost;
use App\Domain\Community\Models\CommunityTopic;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CommunityPost>
 */
final class CommunityPostFactory extends Factory
{
    protected $model = CommunityPost::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'community_topic_id' => CommunityTopic::factory(),
            'user_id' => User::factory(),
            'body' => fake()->paragraph(),
        ];
    }
}
