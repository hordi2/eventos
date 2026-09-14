<?php

declare(strict_types=1);

namespace App\Domain\Form\Actions;

use App\Domain\Form\Models\Form;
use App\Domain\Form\Support\FormSettings;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * Non versionné, contrairement aux champs : un changement d'écran ou de
 * thème s'applique tout de suite, y compris à un formulaire publié, sans
 * toucher à l'interprétation des réponses (règle 4.7 du CLAUDE.md).
 */
final class UpdateFormSettings
{
    /**
     * @param  array<string, mixed>  $settings  réglages déjà validés (FormSettings::rules)
     */
    public function handle(Form $form, User $editor, array $settings): Form
    {
        Gate::forUser($editor)->authorize('update', $form);

        $current = FormSettings::resolve($form->settings);

        foreach ($settings as $section => $values) {
            if (! is_array($values) || ! isset($current[$section])) {
                continue;
            }

            foreach ($values as $key => $value) {
                // Les chemins d'image ne s'écrivent que par l'envoi d'une image.
                if (array_key_exists($key, $current[$section]) && ! in_array($key, FormSettings::IMAGE_KINDS, true)) {
                    $current[$section][$key] = $value;
                }
            }
        }

        $form->update(['settings' => $current]);

        return $form->refresh();
    }
}
