<?php

declare(strict_types=1);

namespace App\Support\CheckIn;

use Illuminate\Support\Facades\DB;

/**
 * Nom de table d'un invité identifié directement par (guest_type, guest_id)
 * — contrairement à GetContactTableName (variable de fusion des e-mails,
 * qui ne connaît qu'un Contact), utilisable aussi bien pour un invité RSVP
 * que pour un billet payé : SeatAssignment ne distingue pas les deux.
 */
final class GetGuestTableName
{
    public function forGuest(int $organizationId, int $eventId, string $guestType, int $guestId): ?string
    {
        return DB::table('seat_assignments')
            ->join('seating_tables', 'seating_tables.id', '=', 'seat_assignments.seating_table_id')
            ->where('seat_assignments.organization_id', $organizationId)
            ->where('seat_assignments.event_id', $eventId)
            ->where('seat_assignments.guest_type', $guestType)
            ->where('seat_assignments.guest_id', $guestId)
            ->value('seating_tables.name');
    }
}
