<?php

declare(strict_types=1);

namespace App\Support\Casts;

use App\Support\Money;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Cast Eloquent pour Money, qui occupe deux colonnes (montant + devise)
 * plutôt qu'une seule. Par défaut : amount_minor / currency. Pour un modèle
 * avec plusieurs montants, préciser les colonnes via les arguments du cast :
 *
 *   protected function casts(): array
 *   {
 *       return ['price' => AsMoney::class.':price_amount_minor,price_currency'];
 *   }
 *
 * L'interface déclare set() comme acceptant Money, mais PHP n'a pas de
 * generics réels : rien n'empêche $model->fee = 'autre chose' à l'exécution.
 * On type donc set() en mixed pour garder le contrôle instanceof ci-dessous
 * significatif (sans ça, PHPStan le considère toujours vrai et le signale).
 *
 * @implements CastsAttributes<Money|null, mixed>
 */
final class AsMoney implements CastsAttributes
{
    public function __construct(
        private readonly string $amountColumn = 'amount_minor',
        private readonly string $currencyColumn = 'currency',
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?Money
    {
        $amount = $attributes[$this->amountColumn] ?? null;
        $currency = $attributes[$this->currencyColumn] ?? null;

        if ($amount === null || $currency === null) {
            return null;
        }

        return Money::fromMinorUnits((int) $amount, (string) $currency);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): array
    {
        if ($value === null) {
            return [
                $this->amountColumn => null,
                $this->currencyColumn => null,
            ];
        }

        if (! $value instanceof Money) {
            throw new InvalidArgumentException("L'attribut {$key} doit être une instance de Money.");
        }

        return [
            $this->amountColumn => $value->amountMinor(),
            $this->currencyColumn => $value->currency(),
        ];
    }
}
