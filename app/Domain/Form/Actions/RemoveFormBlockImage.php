<?php

declare(strict_types=1);

namespace App\Domain\Form\Actions;

use App\Domain\Form\Models\Form;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

/**
 * Supprime l'image d'un bloc, quand l'organisateur la retire ou la remplace.
 * Le chemin vient du navigateur : seul un fichier du dossier de CE
 * formulaire peut être supprimé.
 */
final class RemoveFormBlockImage
{
    public function handle(Form $form, User $editor, string $path): void
    {
        Gate::forUser($editor)->authorize('update', $form);

        if (! str_starts_with($path, SaveFormBlockImage::directory($form).'/') || str_contains($path, '..')) {
            return;
        }

        Storage::disk('public')->delete($path);
    }
}
