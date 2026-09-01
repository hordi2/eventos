<?php

declare(strict_types=1);

namespace App\Domain\Form\Actions;

use App\Domain\Form\Models\FieldOption;
use App\Domain\Form\Models\FormField;
use App\Domain\Form\Models\FormVersion;
use Illuminate\Support\Str;

/**
 * Crée les FormField (et FieldOption) d'une FormVersion à partir d'un
 * tableau de définitions. Utilisé par CreateForm, UpdateFormDraft et
 * ReviseForm — jamais appelé directement en dehors de ces actions.
 */
final class WriteFormFields
{
    /**
     * @param  array<int, array<string, mixed>>  $fields
     */
    public function handle(FormVersion $version, array $fields): void
    {
        $usedKeys = [];

        foreach (array_values($fields) as $position => $fieldData) {
            $key = $this->resolveKey($fieldData['key'] ?? null, $fieldData['label'], $usedKeys);
            $usedKeys[] = $key;

            $field = FormField::query()->create([
                'organization_id' => $version->organization_id,
                'form_version_id' => $version->id,
                'key' => $key,
                'type' => $fieldData['type'],
                'label' => $fieldData['label'],
                'help_text' => $fieldData['help_text'] ?? null,
                'is_required' => $fieldData['is_required'] ?? false,
                'position' => $position,
                'config' => $fieldData['config'] ?? null,
            ]);

            $this->writeOptions($field, $fieldData['options'] ?? []);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $options
     */
    private function writeOptions(FormField $field, array $options): void
    {
        foreach (array_values($options) as $position => $optionData) {
            FieldOption::query()->create([
                'organization_id' => $field->organization_id,
                'form_field_id' => $field->id,
                'value' => $optionData['value'] ?? Str::slug($optionData['label'], '_'),
                'label' => $optionData['label'],
                'position' => $position,
                'quota' => $optionData['quota'] ?? null,
            ]);
        }
    }

    /**
     * @param  list<string>  $usedKeys
     */
    private function resolveKey(?string $providedKey, string $label, array $usedKeys): string
    {
        $key = $providedKey ?? Str::slug($label, '_');
        $original = $key;
        $suffix = 1;

        while (in_array($key, $usedKeys, true)) {
            $key = "{$original}_{$suffix}";
            $suffix++;
        }

        return $key;
    }
}
