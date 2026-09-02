<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Contact\Models\Contact;
use App\Domain\Organization\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Contact>
 */
final class ContactFactory extends Factory
{
    protected $model = Contact::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
        ];
    }

    public function withEmailConsent(): static
    {
        return $this->state(fn (): array => [
            'email_consent' => true,
            'email_consent_source' => 'registration',
            'email_consent_at' => now(),
        ]);
    }
}
