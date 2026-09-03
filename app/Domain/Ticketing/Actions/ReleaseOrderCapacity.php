<?php

declare(strict_types=1);

namespace App\Domain\Ticketing\Actions;

use App\Domain\Ticketing\Models\Order;
use App\Domain\Ticketing\Models\PriceTier;
use App\Domain\Ticketing\Models\TicketType;

/**
 * Libère, pour chaque ligne d'une commande, le quota tenu au niveau du type
 * de billet et — s'il y en a un — du palier de tarification. Reconstruit
 * les mêmes clés de réservation que CreateOrder ("{reservation_key}:type:{id}"
 * et "{reservation_key}:tier:{id}") plutôt que de les stocker séparément :
 * elles sont entièrement déterminées par la commande et ses lignes.
 */
final class ReleaseOrderCapacity
{
    public function __construct(
        private readonly ReleaseTicketTypeCapacity $releaseTicketTypeCapacity,
        private readonly ReleasePriceTierCapacity $releasePriceTierCapacity,
    ) {}

    public function handle(Order $order): void
    {
        foreach ($order->items()->with(['ticketType', 'priceTier'])->get() as $item) {
            $ticketType = $item->ticketType;

            if ($ticketType instanceof TicketType) {
                $this->releaseTicketTypeCapacity->handle($ticketType, "{$order->reservation_key}:type:{$ticketType->id}");
            }

            $priceTier = $item->priceTier;

            if ($priceTier instanceof PriceTier) {
                $this->releasePriceTierCapacity->handle($priceTier, "{$order->reservation_key}:tier:{$priceTier->id}");
            }
        }
    }
}
