<?php

declare(strict_types=1);

namespace App\Domain\Form\Support;

use App\Domain\Form\Actions\SyncSubEventRegistrations;
use App\Domain\Form\Models\FieldType;
use App\Domain\Form\Models\FormField;
use App\Support\Money;
use Closure;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

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
     * @param  bool|null  $required  obligation calculée ailleurs (logique conditionnelle, don) ; à défaut, celle du champ
     * @return array<string, list<mixed>>
     */
    public function forField(FormField $field, ?bool $required = null): array
    {
        $presence = ($required ?? $field->is_required) && $field->type !== FieldType::InformationalText
            ? 'required'
            : 'nullable';

        $typeRules = $this->rulesForType($field, $presence);
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
    private function rulesForType(FormField $field, string $presence): array
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
            FieldType::Dropdown => [$field->key => [Rule::in($field->options->pluck('value'))]],
            FieldType::DateTime => [$field->key => ['date']],
            FieldType::Url, FieldType::SocialProfile => [$field->key => ['string', 'max:2048', 'url:http,https']],
            FieldType::Quantity => [$field->key => ['integer', 'min:'.(int) ($config['min'] ?? 0), 'max:'.(int) ($config['max'] ?? 99)]],
            FieldType::PostalAddress => $this->postalAddressRules($field->key, $presence),
            FieldType::SubEvents => [
                $field->key => ['array'],
                "{$field->key}.*" => ['integer', Rule::in(SyncSubEventRegistrations::offeredIds($config))],
            ],
            FieldType::Donation => $this->donationRules($field, $presence),
            FieldType::DonorInfo => [
                $field->key => ['array'],
                "{$field->key}.name" => [$presence, 'string', 'max:255'],
                "{$field->key}.company" => ['nullable', 'string', 'max:255'],
                ...$this->postalAddressRules($field->key, $presence),
                "{$field->key}.anonymous" => ['nullable', 'boolean'],
            ],
        };
    }

    /**
     * Adresse saisie en plusieurs lignes : « obligatoire » exige au moins la
     * rue et la ville, le reste peut manquer (tous les pays n'ont pas de code
     * postal).
     *
     * @return array<string, list<mixed>>
     */
    private function postalAddressRules(string $key, string $presence): array
    {
        return [
            $key => ['array'],
            "{$key}.line1" => [$presence, 'string', 'max:255'],
            "{$key}.line2" => ['nullable', 'string', 'max:255'],
            "{$key}.city" => [$presence, 'string', 'max:120'],
            "{$key}.region" => ['nullable', 'string', 'max:120'],
            "{$key}.postal_code" => ['nullable', 'string', 'max:20'],
            "{$key}.country" => ['nullable', 'string', 'max:120'],
        ];
    }

    /**
     * Un montant proposé, « autre » avec un montant lisible dans la devise
     * du bloc, ou rien quand le don est facultatif.
     *
     * @return array<string, list<mixed>>
     */
    private function donationRules(FormField $field, string $presence): array
    {
        $config = $field->config ?? [];
        $currency = DonationAnswer::currency($config);
        $choices = array_map(strval(...), DonationAnswer::suggestedAmounts($config));

        if (DonationAnswer::allowsCustom($config)) {
            $choices[] = DonationAnswer::CUSTOM;
        }

        return [
            $field->key => ['array'],
            "{$field->key}.choice" => [$presence, 'string', Rule::in($choices)],
            "{$field->key}.custom" => [
                "exclude_unless:{$field->key}.choice,".DonationAnswer::CUSTOM,
                'required',
                'string',
                'max:20',
                function (string $attribute, mixed $value, Closure $fail) use ($currency): void {
                    try {
                        $amount = Money::parse((string) $value, $currency);
                    } catch (InvalidArgumentException) {
                        $fail(Money::decimals($currency) === 0
                            ? 'Indiquez un montant en chiffres, sans décimales (par exemple 5000).'
                            : 'Indiquez un montant en chiffres (par exemple 25 ou 25,50).');

                        return;
                    }

                    if (! $amount->isPositive()) {
                        $fail('Le montant du don doit être supérieur à zéro.');
                    }
                },
            ],
        ];
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
