<?php

declare(strict_types=1);

namespace App\Domain\Form\Actions;

use App\Domain\Form\Models\Form;
use App\Domain\Form\Support\FormSettings;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * Retire le logo ou le fond du thème sans toucher au fichier : l'image reste
 * dans « Mes images », d'où elle peut être réutilisée ou supprimée pour de
 * bon (DeleteOrganizationImage).
 */
final class RemoveFormThemeImage
{
    public function handle(Form $form, User $editor, string $kind): Form
    {
        Gate::forUser($editor)->authorize('update', $form);

        $settings = FormSettings::resolve($form->settings);
        $settings['theme'][FormSettings::IMAGE_KINDS[$kind]] = null;

        $form->update(['settings' => $settings]);

        return $form->refresh();
    }
}
