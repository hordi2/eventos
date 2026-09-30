<?php

declare(strict_types=1);

namespace App\Support\Registration;

use App\Domain\Event\Models\Event;
use App\Domain\Form\Models\Registration;
use Illuminate\Support\Facades\URL;

/**
 * Lien personnel vers « mon agenda » (D6). Signé, jamais authentifié —
 * comme la modification d'inscription. Il reste valable un mois après la
 * fin de l'événement, et non jusqu'à la date limite de modification : un
 * participant consulte son programme pendant l'événement, bien après
 * qu'il soit trop tard pour changer sa réponse.
 */
final class PersonalAgendaLink
{
    public function url(Event $event, Registration $registration): string
    {
        return $this->signed('guest.registration.agenda', $event, $registration);
    }

    public function icsUrl(Event $event, Registration $registration): string
    {
        return $this->signed('guest.registration.agenda.ics', $event, $registration);
    }

    private function signed(string $route, Event $event, Registration $registration): string
    {
        $event->loadMissing('organization');

        return URL::temporarySignedRoute(
            $route,
            $event->end_at->addMonth(),
            [$event->organization->slug, $event->slug, $registration->id],
        );
    }
}
