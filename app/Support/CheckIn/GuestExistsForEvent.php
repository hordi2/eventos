<?php

declare(strict_types=1);

namespace App\Support\CheckIn;

use Illuminate\Support\Facades\DB;

/**
 * Empêche un poste de check-in de valider un attendee_id/ticket_id qui
 * appartient à un autre événement (ou une autre organisation) — même
 * raison d'être hors Domain/CheckIn que GetEventGuestList (voir son
 * docblock).
 */
final class GuestExistsForEvent
{
    public function handle(int $eventId, ?int $attendeeId, ?int $ticketId): bool
    {
        if ($attendeeId !== null) {
            return DB::table('attendees')
                ->join('registrations', 'registrations.id', '=', 'attendees.registration_id')
                ->where('attendees.id', $attendeeId)
                ->where('registrations.event_id', $eventId)
                ->whereNull('attendees.deleted_at')
                ->whereNull('registrations.deleted_at')
                ->exists();
        }

        return DB::table('tickets')
            ->join('order_items', 'order_items.id', '=', 'tickets.order_item_id')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('tickets.id', $ticketId)
            ->where('orders.event_id', $eventId)
            ->whereNull('tickets.deleted_at')
            ->whereNull('orders.deleted_at')
            ->exists();
    }
}
