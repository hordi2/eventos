<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\CheckIn\Models\BadgeSettings;
use App\Domain\Organization\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BadgeSettings>
 */
final class BadgeSettingsFactory extends Factory
{
    protected $model = BadgeSettings::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'event_id' => fake()->numberBetween(1, 1000),
            'logo_path' => null,
        ];
    }
}
