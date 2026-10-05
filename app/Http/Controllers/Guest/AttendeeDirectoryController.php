<?php

declare(strict_types=1);

namespace App\Http\Controllers\Guest;

use App\Domain\Event\Models\Event;
use App\Domain\Form\Models\Registration;
use App\Domain\Form\Models\RegistrationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Guest\JoinAttendeeDirectoryRequest;
use App\Support\Networking\GetAttendeeDirectory;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Annuaire des participants (D8), côté invité.
 *
 * On n'y entre que par son propre lien signé, et seulement si l'organisateur
 * a ouvert l'annuaire. Un participant choisit d'y figurer, écrit la ligne
 * qui le présente, et peut en sortir à tout moment : son consentement est
 * alors effacé, pas seulement masqué.
 */
final class AttendeeDirectoryController extends Controller
{
    public function show(Request $request, string $organization, string $event, int $registration, GetAttendeeDirectory $getAttendeeDirectory): View
    {
        $eventModel = $this->event($request);
        $registrationModel = $this->registration($eventModel, $registration);

        abort_unless($eventModel->has_attendee_directory, 404);

        return view('guest.registration.directory', [
            'event' => $eventModel,
            'registration' => $registrationModel,
            'attendees' => $getAttendeeDirectory->handle($eventModel),
            'joinUrl' => $request->fullUrl(),
        ]);
    }

    public function update(JoinAttendeeDirectoryRequest $request, string $organization, string $event, int $registration): RedirectResponse
    {
        $eventModel = $this->event($request);
        $registrationModel = $this->registration($eventModel, $registration);

        abort_unless($eventModel->has_attendee_directory, 404);

        $joining = $request->boolean('join');

        $registrationModel->update([
            // Retrait : le consentement est effacé, et la ligne avec lui.
            'directory_consent_at' => $joining ? CarbonImmutable::now() : null,
            'directory_headline' => $joining ? ($request->string('headline')->toString() ?: null) : null,
        ]);

        return back()->with('status', $joining ? 'directory-joined' : 'directory-left');
    }

    private function event(Request $request): Event
    {
        $event = $request->attributes->get('guestEvent');
        abort_unless($event instanceof Event, 404);

        return $event;
    }

    /**
     * L'inscription doit être celle de cet événement, et confirmée : un
     * refus ou une annulation n'a rien à faire dans l'annuaire.
     */
    private function registration(Event $event, int $registration): Registration
    {
        $model = Registration::query()
            ->where('event_id', $event->id)
            ->whereKey($registration)
            ->firstOrFail();

        abort_unless($model->status === RegistrationStatus::Confirmed, 404);

        return $model;
    }
}
