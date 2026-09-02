<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Form\Models\Registration;
use App\Domain\Form\Models\RegistrationRevision;
use App\Domain\Organization\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RegistrationRevision>
 */
final class RegistrationRevisionFactory extends Factory
{
    protected $model = RegistrationRevision::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'registration_id' => Registration::factory(),
            'snapshot' => ['email' => fake()->safeEmail()],
            'created_at' => now(),
        ];
    }
}
