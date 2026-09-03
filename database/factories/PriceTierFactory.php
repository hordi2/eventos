<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Organization\Models\Organization;
use App\Domain\Ticketing\Models\PriceTier;
use App\Support\Money;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PriceTier>
 */
final class PriceTierFactory extends Factory
{
    protected $model = PriceTier::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'ticket_type_id' => TicketTypeFactory::new(),
            'name' => 'Tarif normal',
            'amount' => Money::fromMinorUnits(2000, 'EUR'),
            'quantity' => null,
            'starts_at' => null,
            'ends_at' => null,
            'position' => 0,
        ];
    }

    public function earlyBird(): static
    {
        return $this->state(fn (): array => [
            'name' => 'Early bird',
            'amount' => Money::fromMinorUnits(1500, 'EUR'),
            'position' => 0,
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (): array => ['ends_at' => now()->subDay()]);
    }

    public function upcoming(): static
    {
        return $this->state(fn (): array => ['starts_at' => now()->addDay()]);
    }

    public function limited(int $quantity): static
    {
        return $this->state(fn (): array => ['quantity' => $quantity]);
    }
}
