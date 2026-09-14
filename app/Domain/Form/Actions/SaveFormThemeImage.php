<?php

declare(strict_types=1);

namespace App\Domain\Form\Actions;

use App\Domain\Form\Models\Form;
use App\Domain\Form\Support\FormSettings;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Logo ou image de fond du thème. Disque « public », comme la bannière de la
 * page événement (SavePageBanner) : l'image est servie directement aux
 * invités. L'ancienne image est effacée pour ne pas accumuler de fichiers
 * orphelins.
 */
final class SaveFormThemeImage
{
    public function handle(Form $form, User $editor, string $kind, UploadedFile $image): Form
    {
        Gate::forUser($editor)->authorize('update', $form);

        $key = FormSettings::IMAGE_KINDS[$kind];
        $settings = FormSettings::resolve($form->settings);
        $previousPath = $settings['theme'][$key];

        if (is_string($previousPath)) {
            Storage::disk('public')->delete($previousPath);
        }

        $settings['theme'][$key] = $image->storeAs('form-themes', Str::uuid().'.'.$image->extension(), 'public');

        $form->update(['settings' => $settings]);

        return $form->refresh();
    }
}
