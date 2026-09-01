<?php

declare(strict_types=1);

namespace App\Domain\Form\Support;

use App\Domain\Form\Models\FieldType;
use App\Domain\Form\Models\FormField;
use Illuminate\Validation\Rule;

/**
 * Génère les règles de validation Laravel (serveur) propres à chaque type de
 * champ MVP (M2.1 du CDC), au format directement injectable dans
 * Validator::make() — clé = chemin du champ, valeur = liste de règles.
 * Réutilisable telle quelle par un Form Request une fois que le module
 * Inscriptions (T-030) existera pour de vrai.
 */
final class FieldValidationRules
{
    /**
     * @return array<string, list<mixed>>
     */
    public function forField(FormField $field): array
    {
        $presence = $field->is_required && $field->type !== FieldType::InformationalText
            ? 'required'
            : 'nullable';

        $typeRules = $this->rulesForType($field);
        $rules = [$field->key => [$presence, ...($typeRules[$field->key] ?? [])]];

        foreach ($typeRules as $path => $pathRules) {
            if ($path !== $field->key) {
                $rules[$path] = $pathRules;
            }
        }

        return $rules;
    }

    /**
     * @return array<string, list<mixed>>
     */
    private function rulesForType(FormField $field): array
    {
        $config = $field->config ?? [];

        return match ($field->type) {
            FieldType::ShortText => [$field->key => $this->textRules($config, 255)],
            FieldType::LongText => [$field->key => $this->textRules($config, null)],
            FieldType::Number => [$field->key => $this->numberRules($config)],
            FieldType::Email => [$field->key => ['email:rfc,dns']],
            FieldType::Phone => [$field->key => ['string']],
            FieldType::Date => [$field->key => $this->dateRules($config)],
            FieldType::SingleChoice => [$field->key => [Rule::in($field->options->pluck('value'))]],
            FieldType::MealChoice => [$field->key => [Rule::in($field->options->pluck('value'))]],
            FieldType::MultipleChoice => $this->multipleChoiceRules($field, $config),
            FieldType::YesNo => [$field->key => ['boolean']],
            FieldType::Consent => [$field->key => ['accepted']],
            FieldType::InformationalText => [$field->key => ['prohibited']],
        };
    }

    /**
     * @param  array<string, mixed>  $config
     * @return list<mixed>
     */
    private function textRules(array $config, ?int $defaultMax): array
    {
        $rules = ['string'];

        if (isset($config['min_length'])) {
            $rules[] = 'min:'.$config['min_length'];
        }

        $max = $config['max_length'] ?? $defaultMax;
        if ($max !== null) {
            $rules[] = 'max:'.$max;
        }

        if (isset($config['pattern'])) {
            $rules[] = 'regex:'.$config['pattern'];
        }

        return $rules;
    }

    /**
     * @param  array<string, mixed>  $config
     * @return list<mixed>
     */
    private function numberRules(array $config): array
    {
        $rules = ['numeric'];

        if (isset($config['min'])) {
            $rules[] = 'min:'.$config['min'];
        }

        if (isset($config['max'])) {
            $rules[] = 'max:'.$config['max'];
        }

        return $rules;
    }

    /**
     * @param  array<string, mixed>  $config
     * @return list<mixed>
     */
    private function dateRules(array $config): array
    {
        $rules = ['date'];

        if (isset($config['min_date'])) {
            $rules[] = 'after_or_equal:'.$config['min_date'];
        }

        if (isset($config['max_date'])) {
            $rules[] = 'before_or_equal:'.$config['max_date'];
        }

        return $rules;
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, list<mixed>>
     */
    private function multipleChoiceRules(FormField $field, array $config): array
    {
        $baseRules = ['array'];

        if (isset($config['min_selections'])) {
            $baseRules[] = 'min:'.$config['min_selections'];
        }

        if (isset($config['max_selections'])) {
            $baseRules[] = 'max:'.$config['max_selections'];
        }

        return [
            $field->key => $baseRules,
            "{$field->key}.*" => [Rule::in($field->options->pluck('value'))],
        ];
    }
}
