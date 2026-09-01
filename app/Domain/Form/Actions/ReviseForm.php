<?php

declare(strict_types=1);

namespace App\Domain\Form\Actions;

use App\Domain\Form\InvalidFormVersionTransitionException;
use App\Domain\Form\Models\Form;
use App\Domain\Form\Models\FormVersion;
use App\Domain\Form\Models\FormVersionStatus;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * Crée une nouvelle version d'un formulaire déjà publié, sans jamais
 * toucher aux versions précédentes : la CDC (M2.1) comme la règle 4.7 du
 * CLAUDE.md exigent que les réponses déjà collectées restent
 * interprétables après modification. Un champ conservé (même clé qu'avant)
 * peut être renommé ; un champ absent est simplement retiré de la nouvelle
 * version, sans jamais être supprimé de l'ancienne ; une nouvelle clé
 * ajoute un champ.
 */
final class ReviseForm
{
    public function __construct(
        private readonly WriteFormFields $writeFormFields,
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $fields
     */
    public function handle(Form $form, User $editor, array $fields): FormVersion
    {
        Gate::forUser($editor)->authorize('update', $form);

        $latest = $form->latestVersion();

        if ($latest === null || $latest->status !== FormVersionStatus::Published) {
            throw InvalidFormVersionTransitionException::cannotReviseFromDraft();
        }

        $newVersion = FormVersion::query()->create([
            'organization_id' => $form->organization_id,
            'form_id' => $form->id,
            'version_number' => $latest->version_number + 1,
            'status' => FormVersionStatus::Draft,
        ]);

        $this->writeFormFields->handle($newVersion, $fields);

        return $newVersion->refresh();
    }
}
