<?php

declare(strict_types=1);

namespace App\Domain\Form\Actions;

use App\Domain\Form\Models\Form;
use App\Domain\Organization\Actions\StoreOrganizationImage;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

/**
 * Image d'un bloc « Texte, image, vidéo ». Le fichier rejoint la bibliothèque
 * de l'organisation (redimensionné à l'envoi) et le bloc n'en garde que le
 * chemin, dans la version du formulaire (§4.7).
 */
final class SaveFormBlockImage
{
    public function __construct(
        private readonly StoreOrganizationImage $storeOrganizationImage,
    ) {}

    /**
     * @return array{id: int, path: string, url: string, width: int|null, height: int|null}
     */
    public function handle(Form $form, User $editor, UploadedFile $image): array
    {
        Gate::forUser($editor)->authorize('update', $form);

        $stored = $this->storeOrganizationImage->handle($form->organization, $editor, $image);

        return [
            'id' => $stored->id,
            'path' => $stored->path,
            'url' => Storage::disk($stored->disk)->url($stored->path),
            'width' => $stored->width,
            'height' => $stored->height,
        ];
    }
}
