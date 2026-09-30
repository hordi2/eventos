<?php

declare(strict_types=1);

namespace App\Http\Controllers\Guest;

use App\Domain\Event\Models\Event;
use App\Domain\Form\Models\Registration;
use App\Domain\Form\Models\RegistrationStatus;
use App\Http\Controllers\Controller;
use App\Support\GuestList\IdentifyGuestInvitee;
use App\Support\Invitation\BuildInvitationPdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

/**
 * Faire-part en PDF, du côté de l'invité : celui de son lien personnel, ou
 * celui de son inscription (lien signé). Jamais d'authentification — comme
 * le reste du parcours invité.
 */
final class InvitationPdfController extends Controller
{
    public function forInvitee(Request $request, string $organization, string $event, string $invitationToken, IdentifyGuestInvitee $identifyGuestInvitee, BuildInvitationPdf $buildInvitationPdf): Response
    {
        $eventModel = $this->event($request);
        $invitee = $identifyGuestInvitee->byToken($eventModel->id, $invitationToken);
        abort_if($invitee === null, 404);

        return $this->download($buildInvitationPdf->handle($eventModel, $invitee), $eventModel);
    }

    public function forRegistration(Request $request, string $organization, string $event, int $registration, BuildInvitationPdf $buildInvitationPdf): Response
    {
        $eventModel = $this->event($request);

        $registrationModel = Registration::query()
            ->where('event_id', $eventModel->id)
            ->whereNull('parent_registration_id')
            ->findOrFail($registration);

        abort_if(
            in_array($registrationModel->status, [RegistrationStatus::Cancelled, RegistrationStatus::Declined, RegistrationStatus::Rejected], true),
            410,
            __("Cette inscription n'est plus active."),
        );

        return $this->download($buildInvitationPdf->handle($eventModel, null, $registrationModel), $eventModel);
    }

    private function download(string $pdf, Event $event): Response
    {
        $name = Str::slug($event->title) ?: 'invitation';

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"{$name}.pdf\"",
        ]);
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
}
