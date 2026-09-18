<?php

declare(strict_types=1);

namespace App\Support\Images;

use App\Domain\Organization\Models\OrganizationImage;
use Illuminate\Support\Facades\Storage;

/**
 * Liste de l'onglet « Mes images » du constructeur : les plus récentes
 * d'abord, plafonnées — au-delà, la grille deviendrait illisible et la
 * réponse lourde.
 */
final class PresentOrganizationImages
{
    public const LIMIT = 60;

    /**
     * @return array{images: list<array<string, mixed>>, total: int}
     */
    public function handle(): array
    {
        $total = OrganizationImage::query()->count();

        $images = OrganizationImage::query()
            ->latest('id')
            ->limit(self::LIMIT)
            ->get()
            ->map(fn (OrganizationImage $image): array => [
                'id' => $image->id,
                'url' => Storage::disk($image->disk)->url($image->path),
                'path' => $image->path,
                'name' => $image->original_name,
                'width' => $image->width,
                'height' => $image->height,
            ])
            ->all();

        return ['images' => $images, 'total' => $total];
    }
}
