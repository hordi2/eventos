<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Community\Models\CommunityCategory;
use App\Domain\Community\Models\CommunityTopic;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CommunityTopic>
 */
final class CommunityTopicFactory extends Factory
{
    protected $model = CommunityTopic::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'category' => fake()->randomElement(CommunityCategory::cases()),
            'title' => fake()->sentence(6),
            'body' => fake()->paragraph(),
        ];
    }
}
