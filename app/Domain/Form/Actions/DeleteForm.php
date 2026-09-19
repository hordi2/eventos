<?php

declare(strict_types=1);

namespace App\Domain\Form\Actions;

use App\Domain\Form\FormCannotBeDeletedException;
use App\Domain\Form\Models\Form;
use App\Domain\Form\Models\Registration;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * Suppression logique d'un formulaire superflu. Un formulaire qui a reçu des
 * réponses reste en place : ses versions servent à les lire (§4.7).
 */
final class DeleteForm
{
    public function handle(Form $form, User $editor): void
    {
        Gate::forUser($editor)->authorize('delete', $form);

        if ($form->is_default) {
            throw FormCannotBeDeletedException::isDefault();
        }

        if (Registration::query()->whereIn('form_version_id', $form->versions()->select('id'))->exists()) {
            throw FormCannotBeDeletedException::hasResponses();
        }

        $form->delete();
    }
}
