<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Organization\Models\Organization;
use App\Domain\Organization\Models\OrganizationImage;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<OrganizationImage>
 */
final class OrganizationImageFactory extends Factory
{
    protected $model = OrganizationImage::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'uploaded_by' => null,
            'disk' => 'public',
            'path' => OrganizationImage::DIRECTORY.'/1/'.Str::uuid().'.jpg',
            'original_name' => 'affiche.jpg',
            'width' => 1600,
            'height' => 900,
        ];
    }

    /**
     * Image reprise d'avant la bibliothèque : ses dimensions sont inconnues.
     */
    public function withoutDimensions(): self
    {
        return $this->state(fn (): array => ['width' => null, 'height' => null]);
    }
}
