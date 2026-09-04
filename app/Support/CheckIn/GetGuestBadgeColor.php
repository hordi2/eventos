<?php

declare(strict_types=1);

namespace App\Support\CheckIn;

use Illuminate\Support\Facades\DB;

/**
 * Couleur automatique par tag (M7.3, T-064) : seuls les invités RSVP ont un
 * Contact (via registrations.contact_id -> LinkRegistrationToContact,
 * T-040), donc seulement eux peuvent hériter d'une couleur de tag. Un
 * billet payé n'a aujourd'hui aucun lien vers un Contact (voir le docblock
 * de GetEventGuestList) : sa couleur est toujours neutre — signalé comme
 * limitation connue plutôt que construit ici, hors périmètre de ce ticket.
 *
 * Premier tag du contact (par ordre d'attribution) si plusieurs : aucune
 * notion de tag "prioritaire" n'existe dans le modèle de données actuel.
 */
final class GetGuestBadgeColor
{
    public function forAttendee(int $organizationId, int $attendeeId): ?string
    {
        return DB::table('attendees')
            ->join('registrations', 'registrations.id', '=', 'attendees.registration_id')
            ->join('contact_tag', 'contact_tag.contact_id', '=', 'registrations.contact_id')
            ->join('tags', 'tags.id', '=', 'contact_tag.tag_id')
            ->where('attendees.organization_id', $organizationId)
            ->where('attendees.id', $attendeeId)
            ->whereNull('tags.deleted_at')
            ->orderBy('contact_tag.created_at')
            ->value('tags.color');
    }
}
