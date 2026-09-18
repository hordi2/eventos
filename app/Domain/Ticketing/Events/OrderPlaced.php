<?php

declare(strict_types=1);

namespace App\Domain\Ticketing\Events;

use App\Domain\Ticketing\Models\Order;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Émis par CreateOrder une seule fois par commande, après son commit : une
 * même clé de réservation rejouée renvoie la commande existante sans le
 * réémettre.
 */
final class OrderPlaced
{
    use Dispatchable;

    public function __construct(
        public readonly Order $order,
    ) {}
}
