<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Event\Models\Event;
use App\Domain\Organization\Models\Organization;
use App\Domain\Ticketing\Models\TicketType;
use App\Domain\Ticketing\Models\VatMode;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TicketType>
 */
final class TicketTypeFactory extends Factory
{
    protected $model = TicketType::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'event_id' => Event::factory(),
            'created_by' => User::factory(),
            'name' => $this->faker->randomElement(['Billet standard', 'Billet VIP', 'Pass 1 jour']),
            'description' => null,
            'is_free' => false,
            'currency' => 'EUR',
            'min_per_order' => 1,
            'max_per_order' => null,
            'total_quantity' => null,
            'vat_mode' => VatMode::None,
            'vat_rate_bp' => 0,
            // Choix explicite exigé par M5.1 : la factory doit le fixer
            // elle-même, aucune valeur par défaut en base (§4.2, T-050).
            'fees_absorbed' => false,
            'position' => 0,
            'is_active' => true,
        ];
    }

    public function free(): static
    {
        return $this->state(fn (): array => [
            'is_free' => true,
            'vat_mode' => VatMode::None,
            'vat_rate_bp' => 0,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }

    public function withVat(VatMode $mode, int $rateBp): static
    {
        return $this->state(fn (): array => ['vat_mode' => $mode, 'vat_rate_bp' => $rateBp]);
    }
}
