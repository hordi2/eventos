<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Event\Models\Event;
use App\Domain\Form\Models\Form;
use App\Domain\Organization\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Form>
 */
final class FormFactory extends Factory
{
    protected $model = Form::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'event_id' => Event::factory(),
            'created_by' => User::factory(),
            'name' => "Formulaire d'inscription",
            'slug' => fake()->unique()->slug(2),
            // Les fixtures d'inscription créent parfois plusieurs formulaires
            // pour un même événement : un seul peut être celui par défaut.
            'is_default' => false,
        ];
    }
}
