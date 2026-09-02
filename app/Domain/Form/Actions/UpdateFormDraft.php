<?php

declare(strict_types=1);

namespace App\Domain\Form\Actions;

use App\Domain\Form\InvalidFormVersionTransitionException;
use App\Domain\Form\Models\Form;
use App\Domain\Form\Models\FormField;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * Remplace en place les champs de la version courante d'un formulaire —
 * seulement tant que cette version n'a jamais été publiée (aucune réponse
 * ne peut encore lui être interprétée). Une fois publiée, toute
 * modification doit passer par ReviseForm pour créer une nouvelle version
 * sans toucher à l'ancienne.
 */
final class UpdateFormDraft
{
    public function __construct(
        private readonly WriteFormFields $writeFormFields,
        private readonly WriteConditionalRules $writeConditionalRules,
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $fields
     * @param  array<int, array<string, mixed>>  $rules
     */
    public function handle(Form $form, User $editor, array $fields, array $rules = [], ?string $name = null): Form
    {
        Gate::forUser($editor)->authorize('update', $form);

        $version = $form->latestVersion();

        if ($version === null || ! $version->isEditableInPlace()) {
            throw InvalidFormVersionTransitionException::cannotEditPublishedDraft();
        }

        // Supprimer les champs supprime en cascade (FK) les règles qui les
        // ciblaient : pas besoin de les effacer explicitement ici.
        FormField::query()->where('form_version_id', $version->id)->delete();
        $this->writeFormFields->handle($version, $fields);
        $this->writeConditionalRules->handle($version, $rules);

        if ($name !== null) {
            $form->update(['name' => $name]);
        }

        return $form->refresh();
    }
}
