<?php

declare(strict_types=1);

namespace App\Domain\Form\Support;

/**
 * Bloc « Informations sur le donateur » (T-056) : nom, entreprise, adresse
 * et souhait de rester anonyme, repris sur le reçu du don. Saisi à plat
 * ({clé}[name], {clé}[line1]…), enregistré avec l'adresse regroupée. Il ne
 * s'exige que lorsqu'un don est fait (BuildFormValidationRules).
 */
final class DonorInfoAnswer
{
    /**
     * @return array<string, mixed> vide quand rien n'est renseigné
     */
    public static function normalize(mixed $raw): array
    {
        if (! is_array($raw)) {
            return [];
        }

        $text = fn (string $part): string => is_scalar($raw[$part] ?? null) ? trim((string) $raw[$part]) : '';
        $address = [];

        foreach (PostalAddress::PARTS as $part) {
            if ($text($part) !== '') {
                $address[$part] = $text($part);
            }
        }

        $details = array_filter(['name' => $text('name'), 'company' => $text('company')], fn (string $value): bool => $value !== '');

        if ($details === [] && $address === []) {
            return [];
        }

        return [
            ...$details,
            ...($address !== [] ? ['address' => $address] : []),
            'anonymous' => filter_var($raw['anonymous'] ?? false, FILTER_VALIDATE_BOOLEAN),
        ];
    }

    /**
     * @param  array<array-key, mixed>  $value  réponse enregistrée (voir normalize)
     */
    public static function format(array $value): string
    {
        $parts = array_filter([
            is_string($value['name'] ?? null) ? $value['name'] : '',
            is_string($value['company'] ?? null) ? $value['company'] : '',
            PostalAddress::format(is_array($value['address'] ?? null) ? $value['address'] : []),
        ], fn (string $part): bool => $part !== '');

        $text = implode(' — ', $parts);

        return ($value['anonymous'] ?? false) === true ? trim("{$text} (souhaite rester anonyme)") : $text;
    }
}
