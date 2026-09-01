<?php

declare(strict_types=1);

namespace App\Domain\Form\Actions;

use App\Domain\Form\InvalidFieldAnswerException;
use App\Domain\Form\Models\FieldType;
use App\Domain\Form\Models\FormField;
use Carbon\CarbonImmutable;
use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;

/**
 * Convertit une valeur brute déjà validée (FieldValidationRules) en sa forme
 * canonique de stockage — celle qui sera un jour écrite dans une réponse
 * (T-030). Appelé APRÈS la validation Laravel : ne revalide pas, suppose une
 * entrée déjà conforme au type sauf pour ce qu'un ->string()/->numeric()
 * générique ne peut pas garantir (ex. un numéro de téléphone réellement
 * assignable à un pays).
 */
final class NormalizeFieldAnswer
{
    public function handle(FormField $field, mixed $value, ?string $ip = null): mixed
    {
        return match ($field->type) {
            FieldType::ShortText, FieldType::LongText => trim((string) $value),
            FieldType::Number => is_int($value) || ctype_digit((string) $value) ? (int) $value : (float) $value,
            FieldType::Email => mb_strtolower(trim((string) $value)),
            FieldType::Phone => $this->normalizePhone($field, (string) $value),
            FieldType::Date => CarbonImmutable::parse($value)->toIso8601String(),
            FieldType::SingleChoice, FieldType::MealChoice => (string) $value,
            FieldType::MultipleChoice => array_values(array_map(strval(...), (array) $value)),
            FieldType::YesNo => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            FieldType::Consent => $this->normalizeConsent($field, $value, $ip),
            FieldType::InformationalText => null,
        };
    }

    private function normalizePhone(FormField $field, string $rawNumber): string
    {
        $config = $field->config ?? [];
        $defaultRegion = $config['default_country'] ?? null;

        try {
            $phoneUtil = PhoneNumberUtil::getInstance();
            $parsed = $phoneUtil->parse($rawNumber, $defaultRegion);

            if (! $phoneUtil->isValidNumber($parsed)) {
                throw InvalidFieldAnswerException::forField($field->label, "\"{$rawNumber}\" n'est pas un numéro de téléphone valide.");
            }

            return $phoneUtil->format($parsed, PhoneNumberFormat::E164);
        } catch (NumberParseException) {
            throw InvalidFieldAnswerException::forField($field->label, "\"{$rawNumber}\" n'est pas un numéro de téléphone reconnaissable.");
        }
    }

    /**
     * @return array{accepted: bool, accepted_at: string, ip: ?string, legal_text: string}
     */
    private function normalizeConsent(FormField $field, mixed $value, ?string $ip): array
    {
        $accepted = filter_var($value, FILTER_VALIDATE_BOOLEAN);

        if (! $accepted) {
            throw InvalidFieldAnswerException::forField($field->label, 'le consentement doit être explicitement accepté.');
        }

        return [
            'accepted' => true,
            'accepted_at' => CarbonImmutable::now()->toIso8601String(),
            'ip' => $ip,
            'legal_text' => ($field->config ?? [])['legal_text'] ?? $field->label,
        ];
    }
}
