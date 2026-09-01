<?php

declare(strict_types=1);

namespace App\Domain\Form\Actions;

use App\Domain\Form\Models\FieldOption;
use App\Domain\Form\Models\FieldType;
use App\Domain\Form\Models\FormField;

/**
 * Transforme une valeur déjà normalisée (NormalizeFieldAnswer) en texte lisible
 * pour un export (CSV, etc.) : les choix affichent le libellé de l'option
 * plutôt que sa valeur technique, le consentement affiche sa date et son IP.
 */
final class FormatFieldAnswerForExport
{
    public function handle(FormField $field, mixed $normalizedValue): string
    {
        return match ($field->type) {
            FieldType::ShortText, FieldType::LongText, FieldType::Email, FieldType::Phone, FieldType::Date => (string) $normalizedValue,
            FieldType::Number => (string) $normalizedValue,
            FieldType::SingleChoice, FieldType::MealChoice => $this->optionLabel($field, (string) $normalizedValue),
            FieldType::MultipleChoice => collect((array) $normalizedValue)
                ->map(fn (string $value): string => $this->optionLabel($field, $value))
                ->implode(', '),
            FieldType::YesNo => $normalizedValue ? 'Oui' : 'Non',
            FieldType::Consent => $this->consentExport($normalizedValue),
            FieldType::InformationalText => '',
        };
    }

    private function optionLabel(FormField $field, string $value): string
    {
        $option = $field->options->firstWhere('value', $value);

        return $option instanceof FieldOption ? $option->label : $value;
    }

    /**
     * @param  array{accepted: bool, accepted_at: string, ip: ?string}  $consent
     */
    private function consentExport(array $consent): string
    {
        if (! $consent['accepted']) {
            return 'Refusé';
        }

        $suffix = $consent['ip'] !== null ? " depuis {$consent['ip']}" : '';

        return "Accepté le {$consent['accepted_at']}{$suffix}";
    }
}
