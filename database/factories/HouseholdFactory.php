<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Contact\Models\Household;
use App\Domain\Contact\Models\HouseholdType;
use App\Domain\Organization\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Household>
 */
final class HouseholdFactory extends Factory
{
    protected $model = Household::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'name' => 'Famille '.fake()->lastName(),
            'type' => HouseholdType::Family,
        ];
    }

    public function group(): static
    {
        return $this->state(fn (): array => ['name' => fake()->company(), 'type' => HouseholdType::Group]);
    }
}
