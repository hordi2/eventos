<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Event\Models\Event;
use App\Domain\Form\Models\RegistrationDraft;
use App\Domain\Organization\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<RegistrationDraft>
 */
final class RegistrationDraftFactory extends Factory
{
    protected $model = RegistrationDraft::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'event_id' => Event::factory(),
            'form_version_id' => FormVersionFactory::new(),
            'resume_token' => Str::random(40),
        ];
    }
}
