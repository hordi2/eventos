<?php

declare(strict_types=1);

namespace App\Domain\Ticketing;

use App\Domain\Ticketing\Models\OrderStatus;
use RuntimeException;

final class InvalidOrderTransitionException extends RuntimeException
{
    public static function notPending(int $orderId, OrderStatus $actual): self
    {
        return new self("La commande #{$orderId} n'est pas en attente (statut actuel : {$actual->value}).");
    }

    public static function notPaid(int $orderId, OrderStatus $actual): self
    {
        return new self("La commande #{$orderId} n'est pas payée (statut actuel : {$actual->value}), impossible de la rembourser.");
    }
}
