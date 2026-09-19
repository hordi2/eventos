<?php

declare(strict_types=1);

namespace App\Support\Images;

use App\Domain\Form\Models\Form;
use App\Domain\Form\Models\FormField;
use App\Domain\Form\Support\FormSettings;
use App\Domain\Organization\Models\OrganizationImage;

/**
 * Où une image de la bibliothèque est-elle encore utilisée ? Hors des modules
 * (App\Support) parce que la question traverse Domain/Organization et
 * Domain/Form.
 *
 * Toutes les versions de formulaire sont examinées, pas seulement la version
 * courante : une version déjà publiée doit continuer de s'afficher comme
 * l'invité l'a vue (§4.7).
 */
final class FindOrganizationImageUsage
{
    /**
     * @return list<string> emplacements lisibles, vide si l'image ne sert plus
     */
    public function handle(OrganizationImage $image): array
    {
        $usages = [];

        foreach (Form::query()->get() as $form) {
            $theme = FormSettings::resolve($form->settings)['theme'];

            if ($theme['logo_path'] === $image->path) {
                $usages[] = "logo du formulaire « {$form->name} »";
            }

            if ($theme['background_image_path'] === $image->path) {
                $usages[] = "image de fond du formulaire « {$form->name} »";
            }

            if ($theme['header_image_path'] === $image->path) {
                $usages[] = "bandeau du formulaire « {$form->name} »";
            }
        }

        $fields = FormField::query()
            ->with('formVersion.form')
            ->whereRaw("config->>'image_path' = ?", [$image->path])
            ->get();

        foreach ($fields as $field) {
            $formName = $field->formVersion->form->name;
            $usages[] = "bloc « {$field->label} » du formulaire « {$formName} »";
        }

        return array_values(array_unique($usages));
    }
}
