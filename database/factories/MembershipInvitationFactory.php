<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Organization\Models\MembershipInvitation;
use App\Domain\Organization\Models\MembershipRole;
use App\Domain\Organization\Models\Organization;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<MembershipInvitation>
 */
final class MembershipInvitationFactory extends Factory
{
    protected $model = MembershipInvitation::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'email' => fake()->unique()->safeEmail(),
            'role' => MembershipRole::Editor,
            'invitation_token_hash' => MembershipInvitation::hashToken(Str::random(48)),
            'invitation_expires_at' => CarbonImmutable::now()->addDays(MembershipInvitation::INVITATION_VALIDITY_DAYS),
        ];
    }

    public function expired(): static
    {
        return $this->state(fn (): array => ['invitation_expires_at' => CarbonImmutable::now()->subDay()]);
    }
}
