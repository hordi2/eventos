<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Form\Models\FieldType;
use App\Domain\Form\Models\FormField;
use App\Domain\Form\Models\FormVersion;
use App\Domain\Organization\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FormField>
 */
final class FormFieldFactory extends Factory
{
    protected $model = FormField::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'form_version_id' => FormVersion::factory(),
            'key' => fake()->unique()->slug(),
            'type' => FieldType::ShortText,
            'label' => fake()->words(3, true),
            'is_required' => false,
            'position' => 0,
        ];
    }
}
