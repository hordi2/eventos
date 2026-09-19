<?php

declare(strict_types=1);

namespace App\Domain\Form\Actions;

use App\Domain\Form\Models\Form;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Choisit le formulaire qui répond au lien de l'événement. Les liens propres
 * de chaque formulaire, eux, ne changent pas.
 */
final class SetDefaultForm
{
    public function handle(Form $form, User $editor): Form
    {
        Gate::forUser($editor)->authorize('update', $form);

        DB::transaction(function () use ($form): void {
            // L'ancien d'abord : l'index unique n'admet qu'un formulaire par défaut.
            Form::query()->where('event_id', $form->event_id)->whereKeyNot($form->id)->update(['is_default' => false]);
            $form->update(['is_default' => true]);
        });

        return $form->refresh();
    }
}
