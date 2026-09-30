<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Event\Models\Event;
use App\Domain\Event\Models\ProposalCall;
use App\Domain\Organization\Models\Organization;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProposalCall>
 */
final class ProposalCallFactory extends Factory
{
    protected $model = ProposalCall::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'event_id' => Event::factory(),
            'is_open' => false,
            'intro' => 'Proposez un sujet : vous avez trente minutes pour le présenter.',
            'closes_at' => null,
        ];
    }

    public function open(): self
    {
        return $this->state(fn (): array => ['is_open' => true, 'closes_at' => CarbonImmutable::now()->addMonth()]);
    }

    public function closed(): self
    {
        return $this->state(fn (): array => ['is_open' => true, 'closes_at' => CarbonImmutable::now()->subDay()]);
    }
}
