<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Contact\Models\ContactImport;
use App\Domain\Contact\Models\ContactImportRow;
use App\Domain\Contact\Models\ContactImportRowStatus;
use App\Domain\Organization\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContactImportRow>
 */
final class ContactImportRowFactory extends Factory
{
    protected $model = ContactImportRow::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'contact_import_id' => ContactImport::factory(),
            'row_number' => 1,
            'raw_data' => ['Prénom' => fake()->firstName()],
            'status' => ContactImportRowStatus::Accepted,
        ];
    }
}
