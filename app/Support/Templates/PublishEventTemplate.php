<?php

declare(strict_types=1);

namespace App\Support\Templates;

use App\Domain\Event\Models\Event;
use App\Domain\Event\Models\EventTemplate;
use App\Domain\Form\Models\Form;
use App\Domain\Form\Models\FormField;
use App\Domain\Form\Models\FormVersion;
use App\Domain\Form\Models\FormVersionStatus;
use App\Domain\Page\Models\Page;
use App\Domain\Page\Support\PageBlocks;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * Publier un événement comme modèle dans la bibliothèque communautaire (D11).
 *
 * Ne part que la structure : les champs du formulaire publié, les blocs de la
 * page, les réglages d'inscription. Jamais d'invités, jamais de dates, jamais
 * de montants, jamais d'images — ce qui se partage, c'est une façon de faire,
 * pas les données de personne ni les fichiers d'une organisation.
 *
 * Traverse Event, Form et Page : sa place est dans Support (section 3 du
 * CLAUDE.md).
 */
final class PublishEventTemplate
{
    public function handle(Event $event, User $publisher, string $name, ?string $summary): EventTemplate
    {
        Gate::forUser($publisher)->authorize('updateEvents', $event->organization);

        return EventTemplate::query()->create([
            'organization_id' => $event->organization_id,
            'published_by' => $publisher->id,
            'name' => $name,
            'slug' => $this->uniqueSlug($name),
            'summary' => $summary,
            'category' => $event->type,
            'payload' => [
                'fields' => $this->fields($event),
                'blocks' => $this->blocks($event),
                'settings' => [
                    'requires_approval' => (bool) $event->requires_approval,
                    'allow_waitlist' => (bool) $event->allow_waitlist,
                    'allow_guest_edit' => (bool) $event->allow_guest_edit,
                    'has_attendee_directory' => (bool) $event->has_attendee_directory,
                    'has_carbon_report' => (bool) $event->has_carbon_report,
                ],
            ],
        ]);
    }

    /**
     * Les champs du formulaire publié de l'événement, sans leurs réponses.
     *
     * @return list<array<string, mixed>>
     */
    private function fields(Event $event): array
    {
        $form = Form::query()->where('event_id', $event->id)->orderByDesc('is_default')->first();

        if ($form === null) {
            return [];
        }

        $version = FormVersion::query()
            ->where('form_id', $form->id)
            ->where('status', FormVersionStatus::Published)
            ->latest('id')
            ->first();

        if ($version === null) {
            return [];
        }

        return FormField::query()
            ->where('form_version_id', $version->id)
            ->orderBy('position')
            ->get()
            ->map(fn (FormField $field): array => [
                'key' => $field->key,
                'type' => $field->type->value,
                'label' => $field->label,
                'help_text' => $field->help_text,
                'is_required' => (bool) $field->is_required,
                'config' => $field->config,
            ])
            ->values()
            ->all();
    }

    /**
     * Les blocs de la page, moins les images : un chemin de fichier ne vaut
     * rien dans la bibliothèque d'une autre organisation.
     *
     * @return list<array<string, mixed>>
     */
    private function blocks(Event $event): array
    {
        $page = Page::query()->where('event_id', $event->id)->first();

        return array_map(
            function (array $block): array {
                unset($block['path'], $block['background'], $block['mediaToken'], $block['mediaName']);

                if (isset($block['items'])) {
                    $block['items'] = array_map(function (array $item): array {
                        unset($item['path']);

                        return $item;
                    }, $block['items']);
                }

                return $block;
            },
            PageBlocks::resolve($page),
        );
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'modele';

        return $base.'-'.Str::lower(Str::random(6));
    }
}
