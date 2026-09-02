<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Contact\Models\ContactImport;
use App\Domain\Contact\Models\ContactImportStatus;
use App\Domain\Organization\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContactImport>
 */
final class ContactImportFactory extends Factory
{
    protected $model = ContactImport::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'created_by' => User::factory(),
            'original_filename' => 'contacts.csv',
            'file_path' => 'contact-imports/'.fake()->uuid().'.csv',
            'headers' => ['Prénom', 'Nom', 'E-mail'],
            'status' => ContactImportStatus::Mapping,
        ];
    }
}
