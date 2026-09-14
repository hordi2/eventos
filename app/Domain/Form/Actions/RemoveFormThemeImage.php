<?php

declare(strict_types=1);

namespace App\Domain\Form\Actions;

use App\Domain\Form\Models\Form;
use App\Domain\Form\Support\FormSettings;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

final class RemoveFormThemeImage
{
    public function handle(Form $form, User $editor, string $kind): Form
    {
        Gate::forUser($editor)->authorize('update', $form);

        $key = FormSettings::IMAGE_KINDS[$kind];
        $settings = FormSettings::resolve($form->settings);
        $path = $settings['theme'][$key];

        if (is_string($path)) {
            Storage::disk('public')->delete($path);
        }

        $settings['theme'][$key] = null;
        $form->update(['settings' => $settings]);

        return $form->refresh();
    }
}
