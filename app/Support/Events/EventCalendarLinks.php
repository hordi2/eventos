<?php

declare(strict_types=1);

namespace App\Support\Events;

use App\Domain\Event\Models\Event;

/**
 * « Ajouter à mon agenda » (lot 2) : un lien Google Agenda, et le fichier
 * .ics pour Apple Calendrier, Outlook et le reste. Les heures partent en
 * UTC, format court du protocole ; l'agenda de l'invité les affiche dans
 * son propre fuseau.
 */
final class EventCalendarLinks
{
    public function googleUrl(Event $event): string
    {
        $event->loadMissing('venue');

        return 'https://calendar.google.com/calendar/render?'.http_build_query([
            'action' => 'TEMPLATE',
            'text' => $event->title,
            'dates' => $event->start_at->utc()->format('Ymd\THis\Z').'/'.$event->end_at->utc()->format('Ymd\THis\Z'),
            'details' => (string) $event->description,
            'location' => $event->is_online ? '' : (string) $event->venue?->address,
            'ctz' => $event->timezone,
        ]);
    }

    public function icsUrl(Event $event): string
    {
        $event->loadMissing('organization');

        return route('guest.registration.calendar', [$event->organization->slug, $event->slug]);
    }
}
