<?php

declare(strict_types=1);

namespace App\Domain\Ticketing;

use RuntimeException;

/**
 * Messages destinés à l'acheteur : ils disent ce qui ne va pas sans jamais
 * révéler l'existence d'un autre code.
 */
final class InvalidPromoCodeException extends RuntimeException
{
    public static function unknown(): self
    {
        return new self("Ce code promo n'existe pas pour cet événement.");
    }

    public static function notOpen(): self
    {
        return new self("Ce code promo n'est plus valable.");
    }

    public static function usedUp(): self
    {
        return new self('Ce code promo a déjà servi le nombre de fois prévu.');
    }

    public static function otherCurrency(): self
    {
        return new self("Ce code promo ne s'applique pas à la devise de ces billets.");
    }
}
