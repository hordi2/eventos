<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Event\Models\Event;
use App\Domain\Form\Models\FileScanStatus;
use App\Domain\Form\Models\RegistrationFile;
use App\Domain\Organization\Models\Organization;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<RegistrationFile>
 */
final class RegistrationFileFactory extends Factory
{
    protected $model = RegistrationFile::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $token = (string) Str::uuid();

        return [
            'organization_id' => Organization::factory(),
            'event_id' => Event::factory(),
            'form_field_id' => null,
            'registration_draft_id' => null,
            'registration_id' => null,
            'token' => $token,
            'disk' => 'local',
            'path' => RegistrationFile::QUARANTINE_DIRECTORY."/1/{$token}.pdf",
            'original_name' => 'justificatif.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 2048,
            'scan_status' => FileScanStatus::Pending,
        ];
    }

    public function failed(): static
    {
        return $this->state(fn (): array => [
            'scan_status' => FileScanStatus::Failed,
            'scanned_at' => CarbonImmutable::now(),
        ]);
    }
}
