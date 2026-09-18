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
            ->map($this->row(...))
            ->all();

        return ['images' => $images, 'total' => $total];
    }

    /**
     * Une image telle que la grille la reçoit — aussi la réponse d'une photo
     * libre tout juste importée, pour qu'elle se choisisse de la même façon.
     *
     * @return array{id: int, url: string, path: string, name: string|null, width: int|null, height: int|null}
     */
    public function row(OrganizationImage $image): array
    {
        return [
            'id' => $image->id,
            'url' => Storage::disk($image->disk)->url($image->path),
            'path' => $image->path,
            'name' => $image->original_name,
            'width' => $image->width,
            'height' => $image->height,
        ];
    }
}
