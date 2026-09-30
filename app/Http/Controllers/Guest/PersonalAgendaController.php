<?php

declare(strict_types=1);

namespace App\Http\Controllers\Guest;

use App\Domain\Event\Models\Event;
use App\Domain\Form\Models\Registration;
use App\Domain\Form\Models\RegistrationStatus;
use App\Http\Controllers\Controller;
use App\Support\Messaging\GenerateEventIcs;
use App\Support\Registration\PersonalAgendaLink;
use App\Support\Registration\PresentPersonalAgenda;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * « Mon agenda » (D6) : le programme personnel d'un participant, celui des
 * sessions qu'il a choisies. Lien signé, jamais authentifié — comme la
 * modification et l'annulation d'inscription : la signature protège
 * {registration} contre toute manipulation.
 */
final class PersonalAgendaController extends Controller
{
    public function show(Request $request, string $organization, string $event, int $registration, PresentPersonalAgenda $presentPersonalAgenda, PersonalAgendaLink $personalAgendaLink): View
    {
        $eventModel = $this->event($request);
        $registrationModel = $this->registration($eventModel, $registration);

        return view('guest.registration.agenda', [
            'event' => $eventModel,
            'registration' => $registrationModel,
            'sessions' => $presentPersonalAgenda->handle($registrationModel, $eventModel),
            'icsUrl' => $personalAgendaLink->icsUrl($eventModel, $registrationModel),
        ]);
    }

    public function calendar(Request $request, string $organization, string $event, int $registration, PresentPersonalAgenda $presentPersonalAgenda, GenerateEventIcs $generateEventIcs): Response
    {
        $eventModel = $this->event($request);
        $registrationModel = $this->registration($eventModel, $registration);

        return response(
            $generateEventIcs->personalAgenda($eventModel, $presentPersonalAgenda->sessions($registrationModel, $eventModel)),
            200,
            [
                'Content-Type' => 'text/calendar; charset=utf-8',
                'Content-Disposition' => 'attachment; filename="mon-agenda.ics"',
            ],
        );
    }

    /**
     * L'événement posé par resolve-guest-event.
     */
    private function event(Request $request): Event
    {
        $event = $request->attributes->get('guestEvent');
        abort_unless($event instanceof Event, 404);

        return $event;
    }

    /**
     * L'inscription principale de ce participant. Une inscription annulée
     * ou refusée n'a plus de programme à consulter.
     */
    private function registration(Event $event, int $id): Registration
    {
        $registration = Registration::query()
            ->where('event_id', $event->id)
            ->whereNull('parent_registration_id')
            ->findOrFail($id);

        abort_if(
            in_array($registration->status, [RegistrationStatus::Cancelled, RegistrationStatus::Declined, RegistrationStatus::Rejected], true),
            410,
            __("Cette inscription n'est plus active."),
        );

        return $registration;
    }
}
