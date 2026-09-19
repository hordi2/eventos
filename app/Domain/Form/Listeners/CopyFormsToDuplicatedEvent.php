<?php

declare(strict_types=1);

namespace App\Domain\Form\Listeners;

use App\Domain\Event\Data\EventDuplicationPart;
use App\Domain\Event\Events\EventDuplicated;
use App\Domain\Form\Actions\PublishFormVersion;
use App\Domain\Form\FormRequiresPaidPlanException;
use App\Domain\Form\Models\ConditionalRule;
use App\Domain\Form\Models\FieldOption;
use App\Domain\Form\Models\FieldType;
use App\Domain\Form\Models\Form;
use App\Domain\Form\Models\FormField;
use App\Domain\Form\Models\FormVersion;
use App\Domain\Form\Models\FormVersionStatus;

/**
 * Recopie les formulaires d'un événement dupliqué, dans leur état : la
 * version publiée arrive publiée, une modification en cours arrive en
 * brouillon par-dessus. Les versions archivées ne servaient qu'à lire les
 * réponses de l'original : elles restent avec lui.
 */
final class CopyFormsToDuplicatedEvent
{
    public function __construct(
        private readonly PublishFormVersion $publishFormVersion,
    ) {}

    public function handle(EventDuplicated $duplication): void
    {
        if (! $duplication->includes(EventDuplicationPart::Forms)) {
            return;
        }

        foreach ($duplication->eventIdMap as $sourceEventId => $copyEventId) {
            $forms = Form::query()->where('event_id', $sourceEventId)->with('currentVersion')->orderBy('id')->get();

            foreach ($forms as $form) {
                $this->copy($form, $copyEventId, $duplication);
            }
        }
    }

    private function copy(Form $source, int $eventId, EventDuplicated $duplication): void
    {
        $copy = Form::query()->create([
            'organization_id' => $source->organization_id,
            'event_id' => $eventId,
            'created_by' => $duplication->duplicator->id,
            'name' => $source->name,
            'slug' => $source->slug,
            'is_default' => $source->is_default,
            'settings' => $source->settings,
        ]);

        $published = $source->currentVersion;
        $latest = $source->latestVersion();

        if ($published !== null) {
            $this->copyVersion($published, $copy, 1, $duplication->eventIdMap);

            try {
                $this->publishFormVersion->handle($copy, $duplication->duplicator);
            } catch (FormRequiresPaidPlanException) {
                // Questions réservées aux plans payants, sur un plan redevenu
                // gratuit : la copie attend en brouillon, le temps de choisir.
                return;
            }
        }

        if ($latest !== null && $latest->status === FormVersionStatus::Draft) {
            $this->copyVersion($latest, $copy, $published === null ? 1 : 2, $duplication->eventIdMap);
        }
    }

    /**
     * @param  array<int, int>  $eventIdMap
     */
    private function copyVersion(FormVersion $source, Form $form, int $versionNumber, array $eventIdMap): void
    {
        $source->loadMissing(['fields.options', 'conditionalRules']);

        $version = FormVersion::query()->create([
            'organization_id' => $form->organization_id,
            'form_id' => $form->id,
            'version_number' => $versionNumber,
            'status' => FormVersionStatus::Draft,
        ]);

        $fieldIds = [];

        foreach ($source->fields as $field) {
            $copy = FormField::query()->create([
                'organization_id' => $version->organization_id,
                'form_version_id' => $version->id,
                'key' => $field->key,
                'type' => $field->type,
                'label' => $field->label,
                'help_text' => $field->help_text,
                'is_required' => $field->is_required,
                'position' => $field->position,
                'config' => $this->config($field, $eventIdMap),
            ]);
            $fieldIds[$field->id] = $copy->id;

            foreach ($field->options as $option) {
                FieldOption::query()->create([
                    'organization_id' => $version->organization_id,
                    'form_field_id' => $copy->id,
                    'value' => $option->value,
                    'label' => $option->label,
                    'position' => $option->position,
                    'quota' => $option->quota,
                ]);
            }
        }

        foreach ($source->conditionalRules as $rule) {
            ConditionalRule::query()->create([
                'organization_id' => $version->organization_id,
                'form_version_id' => $version->id,
                'target_field_id' => $fieldIds[$rule->target_field_id],
                'action' => $rule->action,
                'condition_group' => $rule->condition_group,
            ]);
        }
    }

    /**
     * Le bloc « Événements secondaires » désigne les sessions de l'original :
     * il propose désormais leurs copies.
     *
     * @param  array<int, int>  $eventIdMap
     * @return array<string, mixed>|null
     */
    private function config(FormField $field, array $eventIdMap): ?array
    {
        $config = $field->config;

        if ($field->type !== FieldType::SubEvents || ! is_array($config) || ! is_array($config['sub_events'] ?? null)) {
            return $config;
        }

        $config['sub_events'] = collect($config['sub_events'])
            ->filter(fn (mixed $subEvent): bool => is_array($subEvent) && isset($eventIdMap[(int) ($subEvent['id'] ?? 0)]))
            ->map(fn (array $subEvent): array => [...$subEvent, 'id' => $eventIdMap[(int) $subEvent['id']]])
            ->values()
            ->all();

        return $config;
    }
}
