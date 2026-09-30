<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Event\Models\BudgetCategory;
use App\Domain\Event\Models\BudgetLine;
use App\Domain\Event\Models\BudgetLineKind;
use App\Domain\Event\Models\Event;
use App\Domain\Organization\Models\Organization;
use App\Support\Money;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BudgetLine>
 */
final class BudgetLineFactory extends Factory
{
    protected $model = BudgetLine::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'event_id' => Event::factory(),
            'kind' => BudgetLineKind::Expense,
            'category' => BudgetCategory::Venue,
            'label' => 'Location de la salle',
            'supplier' => null,
            'note' => null,
            'planned' => Money::fromMinorUnits(500_000, 'EUR'),
            'actual' => null,
            'position' => 0,
        ];
    }

    /**
     * Dépense engagée, au montant indiqué.
     */
    public function spent(int $amountMinor, string $currency = 'EUR'): self
    {
        return $this->state(fn (): array => ['actual' => Money::fromMinorUnits($amountMinor, $currency)]);
    }

    public function income(): self
    {
        return $this->state(fn (): array => [
            'kind' => BudgetLineKind::Income,
            'category' => BudgetCategory::Sponsoring,
            'label' => 'Sponsor principal',
        ]);
    }
}
