<?php

declare(strict_types=1);

namespace App\Domain\Ticketing\Events;

use App\Domain\Ticketing\Models\Order;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Émis par MarkOrderPaid après le commit du paiement, une seule fois par
 * commande : une confirmation rejouée par le prestataire ne le réémet pas.
 */
final class OrderPaid
{
    use Dispatchable;

    public function __construct(
        public readonly Order $order,
    ) {}
}
