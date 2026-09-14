<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Organization\Models\Collaborator;
use App\Domain\Organization\Models\Organization;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Collaborator>
 */
final class CollaboratorFactory extends Factory
{
    protected $model = Collaborator::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'email' => fake()->unique()->safeEmail(),
            'invitation_token_hash' => Collaborator::hashToken(Str::random(48)),
            'invitation_expires_at' => CarbonImmutable::now()->addDays(Collaborator::INVITATION_VALIDITY_DAYS),
        ];
    }

    public function accepted(User $user): static
    {
        return $this->state(fn (): array => [
            'email' => $user->email,
            'user_id' => $user->id,
            'accepted_at' => CarbonImmutable::now(),
            'invitation_token_hash' => null,
            'invitation_expires_at' => null,
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (): array => ['invitation_expires_at' => CarbonImmutable::now()->subDay()]);
    }
}
