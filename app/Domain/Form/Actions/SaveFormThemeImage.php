<?php

declare(strict_types=1);

namespace App\Domain\Form\Actions;

use App\Domain\Form\Models\Form;
use App\Domain\Form\Support\FormSettings;
use App\Domain\Organization\Actions\StoreOrganizationImage;
use App\Domain\Organization\Models\OrganizationImage;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;

/**
 * Logo ou image de fond du thème : soit une image envoyée maintenant, soit une
 * image déjà présente dans la bibliothèque de l'organisation. Dans les deux
 * cas le formulaire ne garde qu'un chemin ; le fichier appartient à la
 * bibliothèque, jamais au formulaire — changer de logo n'efface donc plus
 * l'ancien, qui peut servir ailleurs.
 */
final class SaveFormThemeImage
{
    public function __construct(
        private readonly StoreOrganizationImage $storeOrganizationImage,
    ) {}

    public function handle(Form $form, User $editor, string $kind, UploadedFile|OrganizationImage $image): Form
    {
        Gate::forUser($editor)->authorize('update', $form);

        $libraryImage = $image instanceof UploadedFile
            ? $this->storeOrganizationImage->handle($form->organization, $editor, $image)
            : $image;

        $settings = FormSettings::resolve($form->settings);
        $settings['theme'][FormSettings::IMAGE_KINDS[$kind]] = $libraryImage->path;

        $form->update(['settings' => $settings]);

        return $form->refresh();
    }
}
