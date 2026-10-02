<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Event\Models\Event;
use App\Domain\Organization\Models\Organization;
use App\Domain\Page\Models\PageMedia;
use App\Models\User;
use App\Support\Antivirus\FileScanStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PageMedia>
 */
final class PageMediaFactory extends Factory
{
    protected $model = PageMedia::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'event_id' => Event::factory(),
            'uploaded_by' => User::factory(),
            'token' => (string) Str::uuid(),
            'disk' => 'local',
            'path' => 'page-media/quarantine/mot-accueil.mp3',
            'original_name' => 'mot-accueil.mp3',
            'mime_type' => 'audio/mpeg',
            'size_bytes' => 512_000,
            'scan_status' => FileScanStatus::Pending,
            'scanned_at' => null,
        ];
    }

    public function clean(): self
    {
        return $this->state(fn (): array => [
            'path' => 'page-media/mot-accueil.mp3',
            'scan_status' => FileScanStatus::Clean,
            'scanned_at' => CarbonImmutable::now(),
        ]);
    }

    public function infected(): self
    {
        return $this->state(fn (): array => [
            'scan_status' => FileScanStatus::Infected,
            'scanned_at' => CarbonImmutable::now(),
        ]);
    }
}
