<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Form\Models\FormField;
use App\Domain\Form\Models\Registration;
use App\Domain\Form\Models\RegistrationAnswer;
use App\Domain\Organization\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RegistrationAnswer>
 */
final class RegistrationAnswerFactory extends Factory
{
    protected $model = RegistrationAnswer::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'registration_id' => Registration::factory(),
            'form_field_id' => FormField::factory(),
            'value' => fake()->word(),
        ];
    }
}
