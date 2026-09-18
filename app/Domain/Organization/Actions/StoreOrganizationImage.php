<?php

declare(strict_types=1);

namespace App\Domain\Organization\Actions;

use App\Domain\Organization\Models\Organization;
use App\Domain\Organization\Models\OrganizationImage;
use App\Models\User;
use App\Support\Images\ResizeUploadedImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Entrée unique de la bibliothèque d'images : toute image envoyée par un
 * organisateur (logo, fond, bloc) passe ici, y est redimensionnée puis
 * inscrite une fois pour toutes. L'autorisation est vérifiée par l'action
 * appelante, qui seule sait sur quoi porte la modification.
 */
final class StoreOrganizationImage
{
    public function __construct(
        private readonly ResizeUploadedImage $resizeUploadedImage,
    ) {}

    public function handle(Organization $organization, User $uploader, UploadedFile $image): OrganizationImage
    {
        $resized = $this->resizeUploadedImage->handle($image);
        $path = OrganizationImage::DIRECTORY."/{$organization->id}/".Str::uuid().'.'.$resized->extension;

        Storage::disk('public')->put($path, $resized->content);

        return OrganizationImage::query()->create([
            'organization_id' => $organization->id,
            'uploaded_by' => $uploader->id,
            'disk' => 'public',
            'path' => $path,
            'original_name' => $image->getClientOriginalName(),
            'width' => $resized->width,
            'height' => $resized->height,
        ]);
    }
}
