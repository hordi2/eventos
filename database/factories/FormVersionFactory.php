<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Form\Models\Form;
use App\Domain\Form\Models\FormVersion;
use App\Domain\Form\Models\FormVersionStatus;
use App\Domain\Organization\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FormVersion>
 */
final class FormVersionFactory extends Factory
{
    protected $model = FormVersion::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'form_id' => Form::factory(),
            'version_number' => 1,
            'status' => FormVersionStatus::Draft,
        ];
    }

    public function published(): static
    {
        return $this->state(fn (): array => [
            'status' => FormVersionStatus::Published,
            'published_at' => now(),
        ]);
    }

    public function archived(): static
    {
        return $this->state(fn (): array => [
            'status' => FormVersionStatus::Archived,
            'published_at' => now(),
        ]);
    }
}
