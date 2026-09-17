<?php

declare(strict_types=1);

namespace App\Domain\Form\Support;

use App\Domain\Form\Models\FieldType;
use App\Domain\Form\Models\FormField;
use App\Domain\Form\Models\FormVersion;
use App\Support\Money;
use InvalidArgumentException;

/**
 * Bloc « Don » (T-056). Réglages (config) : devise, montants proposés en
 * unité mineure (§4.2), montant libre autorisé ou non, cause soutenue.
 * L'invité répond par {clé}[choice] — un montant proposé, « autre » ou
 * rien — et {clé}[custom] quand il saisit son propre montant. La réponse
 * enregistrée ne garde que le montant et sa devise.
 */
final class DonationAnswer
{
    public const CUSTOM = 'autre';

    public const MAX_SUGGESTED_AMOUNTS = 6;

    /**
     * Devises des marchés prioritaires : Afrique francophone, puis Europe.
     *
     * @var array<string, string>
     */
    public const CURRENCIES = [
        'XAF' => 'Franc CFA (Afrique centrale)',
        'XOF' => "Franc CFA (Afrique de l'Ouest)",
        'CDF' => 'Franc congolais',
        'USD' => 'Dollar américain',
        'EUR' => 'Euro',
    ];

    /**
     * @param  array<string, mixed>  $config
     */
    public static function currency(array $config): string
    {
        $currency = $config['currency'] ?? null;

        return is_string($currency) && array_key_exists($currency, self::CURRENCIES) ? $currency : 'XAF';
    }

    /**
     * @param  array<string, mixed>  $config
     * @return list<int>
     */
    public static function suggestedAmounts(array $config): array
    {
        $amounts = [];

        foreach (is_array($config['amounts'] ?? null) ? $config['amounts'] : [] as $amount) {
            if (is_numeric($amount) && (int) $amount > 0) {
                $amounts[] = (int) $amount;
            }
        }

        $amounts = array_values(array_unique($amounts));
        sort($amounts);

        return array_slice($amounts, 0, self::MAX_SUGGESTED_AMOUNTS);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public static function allowsCustom(array $config): bool
    {
        return (bool) ($config['allow_custom'] ?? true);
    }

    /**
     * Montant choisi par l'invité, ou null quand il ne donne pas — ou que sa
     * saisie est illisible, ce que la validation a alors déjà refusé.
     */
    public static function amountFrom(FormField $field, mixed $raw): ?Money
    {
        if (! is_array($raw)) {
            return null;
        }

        $config = $field->config ?? [];
        $currency = self::currency($config);
        $choice = $raw['choice'] ?? null;

        if ($choice === self::CUSTOM && self::allowsCustom($config)) {
            try {
                $amount = Money::parse(is_scalar($raw['custom'] ?? null) ? (string) $raw['custom'] : '', $currency);
            } catch (InvalidArgumentException) {
                return null;
            }
        } elseif (is_numeric($choice) && in_array((int) $choice, self::suggestedAmounts($config), true)) {
            $amount = Money::fromMinorUnits((int) $choice, $currency);
        } else {
            return null;
        }

        return $amount->isPositive() ? $amount : null;
    }

    /**
     * @return array{amount_minor: int, currency: string}|array{}
     */
    public static function normalize(FormField $field, mixed $raw): array
    {
        $amount = self::amountFrom($field, $raw);

        return $amount === null ? [] : ['amount_minor' => $amount->amountMinor(), 'currency' => $amount->currency()];
    }

    /**
     * Montant d'une réponse enregistrée (voir normalize).
     */
    public static function stored(mixed $value): ?Money
    {
        if (! is_array($value) || ! is_int($value['amount_minor'] ?? null) || ! is_string($value['currency'] ?? null)) {
            return null;
        }

        return Money::fromMinorUnits($value['amount_minor'], $value['currency']);
    }

    /**
     * Vrai quand l'un des blocs « Don » affichés reçoit un montant.
     *
     * @param  array<string, mixed>  $answers
     * @param  array<string, array{visible: bool, required: bool}>  $visibility
     */
    public static function isGiven(FormVersion $version, array $answers, array $visibility): bool
    {
        foreach ($version->fields as $field) {
            if ($field->type === FieldType::Donation
                && ($visibility[$field->key]['visible'] ?? false)
                && self::amountFrom($field, $answers[$field->key] ?? null) !== null) {
                return true;
            }
        }

        return false;
    }
}
