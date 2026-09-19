<?php

declare(strict_types=1);

namespace App\Domain\Ticketing\Listeners;

use App\Domain\Event\Data\EventDuplicationPart;
use App\Domain\Event\Events\EventDuplicated;
use App\Domain\Ticketing\Models\PriceTier;
use App\Domain\Ticketing\Models\TicketType;

/**
 * Recopie les billets et leurs paliers de prix d'un événement dupliqué,
 * dates de vente décalées. Rien de vendu ne suit : les compteurs de places
 * repartent de zéro avec les nouveaux identifiants.
 */
final class CopyTicketTypesToDuplicatedEvent
{
    public function handle(EventDuplicated $duplication): void
    {
        if (! $duplication->includes(EventDuplicationPart::Tickets)) {
            return;
        }

        foreach ($duplication->eventIdMap as $sourceEventId => $copyEventId) {
            $ticketTypes = TicketType::query()->where('event_id', $sourceEventId)->with('priceTiers')->orderBy('position')->get();

            foreach ($ticketTypes as $ticketType) {
                $this->copy($ticketType, $copyEventId, $duplication);
            }
        }
    }

    private function copy(TicketType $source, int $eventId, EventDuplicated $duplication): void
    {
        $copy = TicketType::query()->create([
            'organization_id' => $source->organization_id,
            'event_id' => $eventId,
            'created_by' => $duplication->duplicator->id,
            'name' => $source->name,
            'description' => $source->description,
            'is_free' => $source->is_free,
            'currency' => $source->currency,
            'min_per_order' => $source->min_per_order,
            'max_per_order' => $source->max_per_order,
            'total_quantity' => $source->total_quantity,
            'vat_mode' => $source->vat_mode,
            'vat_rate_bp' => $source->vat_rate_bp,
            'fees_absorbed' => $source->fees_absorbed,
            'position' => $source->position,
            'is_active' => $source->is_active,
        ]);

        foreach ($source->priceTiers as $tier) {
            PriceTier::query()->create([
                'organization_id' => $source->organization_id,
                'ticket_type_id' => $copy->id,
                'name' => $tier->name,
                'amount' => $tier->amount,
                'quantity' => $tier->quantity,
                'starts_at' => $duplication->shift($tier->starts_at),
                'ends_at' => $duplication->shift($tier->ends_at),
                'position' => $tier->position,
            ]);
        }
    }
}
