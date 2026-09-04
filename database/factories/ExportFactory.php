<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Analytics\Models\Export;
use App\Domain\Analytics\Models\ExportStatus;
use App\Domain\Analytics\Models\ExportType;
use App\Domain\Organization\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Export>
 */
final class ExportFactory extends Factory
{
    protected $model = Export::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'event_id' => fake()->numberBetween(1, 1000),
            'type' => ExportType::Contacts,
            'status' => ExportStatus::Pending,
            'columns' => ['first_name', 'last_name'],
            'segment' => null,
            'file_path' => null,
            'row_count' => null,
            'requested_by' => User::factory(),
            'completed_at' => null,
            'expires_at' => null,
        ];
    }

    public function completed(): self
    {
        return $this->state(fn (): array => [
            'status' => ExportStatus::Completed,
            'file_path' => 'exports/demo.csv',
            'row_count' => 2,
            'completed_at' => now(),
            'expires_at' => now()->addDay(),
        ]);
    }

    public function expired(): self
    {
        return $this->state(fn (): array => [
            'status' => ExportStatus::Completed,
            'file_path' => 'exports/demo.csv',
            'row_count' => 2,
            'completed_at' => now()->subDays(2),
            'expires_at' => now()->subDay(),
        ]);
    }
}
