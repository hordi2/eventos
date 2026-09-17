<?php

declare(strict_types=1);

namespace App\Support;

use InvalidArgumentException;
use NumberFormatter;
use RuntimeException;

/**
 * Value object immuable pour manipuler des montants d'argent. Toujours en
 * entier, dans la plus petite unité de la devise (centimes pour EUR/USD/CDF,
 * l'unité elle-même pour XOF/XAF qui n'ont pas de subdivision) — jamais de
 * float ni de decimal, conformément à la règle absolue du projet (§4.2).
 */
final class Money
{
    /**
     * Devises sans subdivision (0 chiffre après la virgule). Toute autre
     * devise est supposée avoir 2 décimales (cas très majoritaire, couvre
     * EUR/USD/CDF).
     *
     * @var array<string, int>
     */
    private const ZERO_DECIMAL_CURRENCIES = [
        'XOF' => 0,
        'XAF' => 0,
    ];

    private function __construct(
        private readonly int $amountMinor,
        private readonly string $currency,
    ) {
        if (! preg_match('/^[A-Z]{3}$/', $currency)) {
            throw new InvalidArgumentException("Code devise invalide : \"{$currency}\" (attendu : ISO 4217, ex. EUR).");
        }
    }

    public static function fromMinorUnits(int $amountMinor, string $currency): self
    {
        return new self($amountMinor, $currency);
    }

    public static function zero(string $currency): self
    {
        return new self(0, $currency);
    }

    /**
     * Montant saisi par une personne, en unités de la devise (« 25 »,
     * « 12,50 », « 10 000 ») : converti en unité mineure par manipulation de
     * chaînes, sans jamais passer par un float. Un montant plus précis que la
     * devise est refusé plutôt qu'arrondi en silence (§4.2).
     *
     * @throws InvalidArgumentException
     */
    public static function parse(string $amount, string $currency): self
    {
        $decimals = self::decimals($currency);
        $compact = preg_replace('/[\s\x{00A0}\x{202F}]+/u', '', $amount) ?? '';

        // « 10.000 » ou « 10,000 » dans une devise sans décimales (XAF, XOF) :
        // le séparateur ne peut être qu'un séparateur de milliers.
        if ($decimals === 0 && preg_match('/^\d{1,3}([.,]\d{3})+$/', $compact) === 1) {
            $compact = str_replace(['.', ','], '', $compact);
        }

        if (preg_match('/^(\d{1,12})(?:[.,](\d+))?$/', $compact, $matches) !== 1) {
            throw new InvalidArgumentException("Montant illisible : \"{$amount}\".");
        }

        $fraction = $matches[2] ?? '';

        if (strlen($fraction) > $decimals) {
            throw new InvalidArgumentException("Montant trop précis pour la devise {$currency} : \"{$amount}\".");
        }

        return new self((int) $matches[1] * (10 ** $decimals) + (int) str_pad($fraction, $decimals, '0'), $currency);
    }

    /**
     * Nombre de chiffres après la virgule de la devise.
     */
    public static function decimals(string $currency): int
    {
        return self::ZERO_DECIMAL_CURRENCIES[$currency] ?? 2;
    }

    public function amountMinor(): int
    {
        return $this->amountMinor;
    }

    public function currency(): string
    {
        return $this->currency;
    }

    public function add(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->amountMinor + $other->amountMinor, $this->currency);
    }

    public function subtract(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->amountMinor - $other->amountMinor, $this->currency);
    }

    public function multipliedBy(int $factor): self
    {
        return new self($this->amountMinor * $factor, $this->currency);
    }

    public function negated(): self
    {
        return new self(-$this->amountMinor, $this->currency);
    }

    /**
     * Répartit le montant selon des ratios entiers, sans jamais perdre un
     * centime : le reliquat dû aux arrondis est distribué un centime à la
     * fois, dans l'ordre, jusqu'à épuisement (algorithme de Martin Fowler).
     *
     * @param  list<int>  $ratios
     * @return list<self>
     */
    public function allocate(array $ratios): array
    {
        if ($ratios === []) {
            throw new InvalidArgumentException('Il faut au moins un ratio de répartition.');
        }

        $total = array_sum($ratios);

        if ($total <= 0) {
            throw new InvalidArgumentException('La somme des ratios doit être strictement positive.');
        }

        $shares = [];
        $remainder = $this->amountMinor;

        foreach ($ratios as $ratio) {
            $share = intdiv($this->amountMinor * $ratio, $total);
            $shares[] = $share;
            $remainder -= $share;
        }

        $count = count($shares);
        $step = $remainder >= 0 ? 1 : -1;

        for ($i = 0; $remainder !== 0; $i = ($i + 1) % $count) {
            $shares[$i] += $step;
            $remainder -= $step;
        }

        return array_map(fn (int $share): self => new self($share, $this->currency), $shares);
    }

    public function equals(self $other): bool
    {
        return $this->amountMinor === $other->amountMinor && $this->currency === $other->currency;
    }

    public function isSameCurrency(self $other): bool
    {
        return $this->currency === $other->currency;
    }

    public function isZero(): bool
    {
        return $this->amountMinor === 0;
    }

    public function isPositive(): bool
    {
        return $this->amountMinor > 0;
    }

    public function isNegative(): bool
    {
        return $this->amountMinor < 0;
    }

    public function greaterThan(self $other): bool
    {
        $this->assertSameCurrency($other);

        return $this->amountMinor > $other->amountMinor;
    }

    public function lessThan(self $other): bool
    {
        $this->assertSameCurrency($other);

        return $this->amountMinor < $other->amountMinor;
    }

    /**
     * Formate le montant pour l'affichage, localisé (séparateurs, symbole,
     * position) via l'extension intl. Seul le point de contact avec cette
     * classe qui manipule un float : NumberFormatter::formatCurrency() en
     * exige un dans sa signature. Le calcul qui produit la valeur affichée
     * (division/modulo ci-dessous) reste entièrement entier ; le float n'est
     * créé qu'au tout dernier moment, pour satisfaire cette seule fonction
     * native, jamais réutilisé ni stocké.
     */
    public function format(string $locale = 'fr'): string
    {
        $exponent = self::decimals($this->currency);
        $divisor = 10 ** $exponent;

        $majorPart = intdiv($this->amountMinor, $divisor);
        $minorPart = abs($this->amountMinor % $divisor);

        $decimalValue = $exponent === 0
            ? (string) $majorPart
            : $majorPart.'.'.str_pad((string) $minorPart, $exponent, '0', STR_PAD_LEFT);

        $formatter = new NumberFormatter($locale, NumberFormatter::CURRENCY);
        $formatted = $formatter->formatCurrency((float) $decimalValue, $this->currency);

        if ($formatted === false) {
            throw new RuntimeException("Impossible de formater le montant pour la devise {$this->currency}.");
        }

        return $formatted;
    }

    private function assertSameCurrency(self $other): void
    {
        if (! $this->isSameCurrency($other)) {
            throw new CurrencyMismatchException($this->currency, $other->currency);
        }
    }
}
