<?php

declare(strict_types=1);

namespace App\Domain\Form\Actions;

use App\Domain\Form\Models\Form;
use App\Models\User;
use App\Support\Images\ResizeUploadedImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Image d'un bloc « Texte, image, vidéo » : redimensionnée à l'envoi puis
 * rangée sous form-blocks/{formulaire}. Son chemin vit dans la configuration
 * du bloc, donc dans la version du formulaire (§4.7) ; le fichier, lui, est
 * public — c'est une image d'illustration montrée à tous les invités.
 */
final class SaveFormBlockImage
{
    public function __construct(
        private readonly ResizeUploadedImage $resizeUploadedImage,
    ) {}

    /**
     * @return array{path: string, url: string, width: int, height: int}
     */
    public function handle(Form $form, User $editor, UploadedFile $image): array
    {
        Gate::forUser($editor)->authorize('update', $form);

        $resized = $this->resizeUploadedImage->handle($image);
        $path = self::directory($form).'/'.Str::uuid().'.'.$resized->extension;

        Storage::disk('public')->put($path, $resized->content);

        return [
            'path' => $path,
            'url' => Storage::disk('public')->url($path),
            'width' => $resized->width,
            'height' => $resized->height,
        ];
    }

    public static function directory(Form $form): string
    {
        return "form-blocks/{$form->id}";
    }
}
