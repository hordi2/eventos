<?php

declare(strict_types=1);

namespace App\Domain\Form\Actions;

use App\Domain\Form\Models\FieldOption;
use App\Domain\Form\Models\FieldType;
use App\Domain\Form\Models\FormField;
use App\Domain\Form\Support\DonationAnswer;
use App\Domain\Form\Support\DonorInfoAnswer;
use App\Domain\Form\Support\PostalAddress;

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
            FieldType::Dropdown => $this->optionLabel($field, (string) $normalizedValue),
            FieldType::DateTime, FieldType::Url, FieldType::SocialProfile, FieldType::Quantity => (string) $normalizedValue,
            FieldType::PostalAddress => PostalAddress::format((array) $normalizedValue),
            FieldType::SubEvents => $this->subEventTitles($field, (array) $normalizedValue),
            FieldType::Donation => DonationAnswer::stored($normalizedValue)?->format() ?? '',
            FieldType::DonorInfo => DonorInfoAnswer::format((array) $normalizedValue),
        };
    }

    /**
     * Titres des sessions tels que publiés avec la version du formulaire
     * (config.sub_events) : une réponse garde son sens même si une session
     * est renommée ensuite (§4.7 du CLAUDE.md).
     *
     * @param  array<int|string, mixed>  $ids
     */
    private function subEventTitles(FormField $field, array $ids): string
    {
        $titles = [];

        foreach ((array) (($field->config ?? [])['sub_events'] ?? []) as $subEvent) {
            if (is_array($subEvent)) {
                $titles[(int) ($subEvent['id'] ?? 0)] = (string) ($subEvent['title'] ?? '');
            }
        }

        return implode(', ', array_map(fn (mixed $id): string => $titles[(int) $id] ?? (string) $id, $ids));
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
