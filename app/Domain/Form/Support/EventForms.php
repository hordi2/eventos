<?php

declare(strict_types=1);

namespace App\Domain\Form\Support;

use App\Domain\Form\Models\Form;
use App\Domain\Form\Models\FormField;
use App\Domain\Form\Models\FormVersion;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Les formulaires d'un événement : chacun a son lien, et celui « par défaut »
 * répond au lien de l'événement lui-même. L'événement n'est désigné que par
 * son identifiant (section 3 du CLAUDE.md).
 */
final class EventForms
{
    /**
     * Formulaires en service, celui par défaut en tête, puis par création.
     *
     * @return Collection<int, Form>
     */
    public function all(int $eventId): Collection
    {
        return Form::query()->where('event_id', $eventId)->orderByDesc('is_default')->orderBy('id')->get();
    }

    /**
     * Les questions de l'événement, tous formulaires confondus : celles de la
     * version publiée de chacun (à défaut, de sa dernière version), le
     * formulaire par défaut en tête. Les réponses se rapprochent par clé
     * (§4.7) : une clé posée par plusieurs formulaires n'apparaît qu'une fois.
     *
     * @return Collection<int, FormField>
     */
    public function referenceFields(int $eventId): Collection
    {
        return Form::query()
            ->where('event_id', $eventId)
            ->with('currentVersion.fields.options')
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->get()
            ->map(fn (Form $form): ?FormVersion => $form->currentVersion ?? $form->latestVersion()?->load('fields.options'))
            ->filter()
            ->flatMap(fn (FormVersion $version): Collection => $version->fields)
            ->unique(fn (FormField $field): string => $field->key)
            ->values();
    }

    /**
     * Toutes les questions jamais posées par l'événement, formulaires et
     * versions confondues — y compris une question retirée depuis une
     * version plus récente. Sert à l'export (§4.7) : une réponse déjà
     * collectée ne doit jamais disparaître de la colonne qui l'affiche
     * simplement parce que la question a depuis été retirée ou renommée.
     * Le libellé retenu pour une clé qui existe dans plusieurs versions est
     * celui de sa version la plus récente.
     *
     * @return Collection<int, FormField>
     */
    public function allFieldsEverUsed(int $eventId): Collection
    {
        return Form::query()
            ->where('event_id', $eventId)
            ->with('versions.fields.options')
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->get()
            ->flatMap(fn (Form $form): Collection => $form->versions->sortByDesc('version_number'))
            ->flatMap(fn (FormVersion $version): Collection => $version->fields)
            ->unique(fn (FormField $field): string => $field->key)
            ->values();
    }

    /**
     * Le formulaire qu'ouvre un lien : celui du slug, sinon celui par défaut.
     */
    public function forLink(int $eventId, ?string $slug): ?Form
    {
        $query = Form::query()->where('event_id', $eventId);

        return ($slug === null ? $query->where('is_default', true) : $query->where('slug', $slug))->first();
    }

    /**
     * Le formulaire d'une version : celui d'un brouillon ou d'une inscription,
     * même supprimé depuis, pour garder son thème et ses réglages.
     */
    public function forVersion(int $formVersionId): ?Form
    {
        $formId = FormVersion::query()->whereKey($formVersionId)->value('form_id');

        return $formId === null ? null : Form::query()->withTrashed()->find($formId);
    }

    public function hasDefault(int $eventId): bool
    {
        return Form::query()->where('event_id', $eventId)->where('is_default', true)->exists();
    }

    /**
     * Un lien lisible tiré du nom, unique parmi les formulaires de l'événement.
     */
    public function uniqueSlug(int $eventId, string $name): string
    {
        $base = Str::limit(Str::slug($name), 70, '') ?: 'formulaire';
        $taken = Form::query()->where('event_id', $eventId)->pluck('slug')->all();
        $slug = $base;

        for ($suffix = 2; in_array($slug, $taken, true); $suffix++) {
            $slug = "{$base}-{$suffix}";
        }

        return $slug;
    }
}
