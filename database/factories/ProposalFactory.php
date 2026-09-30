<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Event\Models\Event;
use App\Domain\Event\Models\Proposal;
use App\Domain\Event\Models\ProposalCall;
use App\Domain\Event\Models\ProposalFormat;
use App\Domain\Event\Models\ProposalStatus;
use App\Domain\Organization\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Proposal>
 */
final class ProposalFactory extends Factory
{
    protected $model = Proposal::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'event_id' => Event::factory(),
            'proposal_call_id' => ProposalCall::factory(),
            'proposer_name' => fake()->name(),
            'proposer_email' => fake()->safeEmail(),
            'proposer_role' => 'Chercheuse',
            'proposer_company' => fake()->company(),
            'proposer_bio' => fake()->paragraph(),
            'title' => 'Payer en Mobile Money sans réseau',
            'summary' => fake()->paragraphs(2, true),
            'format' => ProposalFormat::Talk,
            'duration_minutes' => 30,
            'status' => ProposalStatus::Pending,
        ];
    }

    public function accepted(): self
    {
        return $this->state(fn (): array => ['status' => ProposalStatus::Accepted]);
    }

    public function rejected(): self
    {
        return $this->state(fn (): array => ['status' => ProposalStatus::Rejected]);
    }
}
