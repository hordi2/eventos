<?php

declare(strict_types=1);

namespace App\Support\CheckIn;

use Illuminate\Support\Facades\DB;

/**
 * Numéro/nom de table pour la variable de fusion {{table}} (T-044, T-065) —
 * uniquement pour un invité RSVP (Domain/Form) : un billet payé n'a
 * aujourd'hui aucun lien vers un Contact, même limitation déjà documentée
 * pour la couleur de badge (voir GetGuestBadgeColor).
 */
final class GetContactTableName
{
    public function forContact(int $organizationId, int $eventId, int $contactId): ?string
    {
        return DB::table('registrations')
            ->join('attendees', 'attendees.registration_id', '=', 'registrations.id')
            ->join('seat_assignments', function ($join): void {
                $join->on('seat_assignments.guest_id', '=', 'attendees.id')
                    ->where('seat_assignments.guest_type', 'attendee');
            })
            ->join('seating_tables', 'seating_tables.id', '=', 'seat_assignments.seating_table_id')
            ->where('registrations.organization_id', $organizationId)
            ->where('registrations.event_id', $eventId)
            ->where('registrations.contact_id', $contactId)
            ->where('attendees.is_primary', true)
            ->whereNull('attendees.deleted_at')
            ->whereNull('registrations.deleted_at')
            ->value('seating_tables.name');
    }
}
