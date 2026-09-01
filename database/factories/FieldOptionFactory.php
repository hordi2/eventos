<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Form\Models\FieldOption;
use App\Domain\Form\Models\FormField;
use App\Domain\Organization\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FieldOption>
 */
final class FieldOptionFactory extends Factory
{
    protected $model = FieldOption::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'form_field_id' => FormField::factory(),
            'value' => fake()->unique()->slug(),
            'label' => fake()->words(2, true),
            'position' => 0,
        ];
    }
}
