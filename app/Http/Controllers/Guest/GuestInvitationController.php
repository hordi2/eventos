<?php

declare(strict_types=1);

namespace App\Http\Controllers\Guest;

use App\Domain\Contact\Models\EventInvitee;
use App\Domain\Event\Models\Event;
use App\Http\Controllers\Controller;
use App\Http\Requests\Guest\FindInvitationRequest;
use App\Support\GuestList\IdentifyGuestInvitee;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Accès d'un invité à son invitation : par son lien personnel, ou en la
 * retrouvant avec son e-mail ou son numéro WhatsApp. L'invitation retenue en
 * session ouvre un événement réservé à sa liste (ResolveGuestEvent).
 */
final class GuestInvitationController extends Controller
{
    public function open(Request $request, string $organization, string $event, string $invitationToken, IdentifyGuestInvitee $identifyGuestInvitee): RedirectResponse
    {
        $invitee = $identifyGuestInvitee->byToken($this->event($request)->id, $invitationToken);

        if ($invitee === null) {
            return redirect()->route('guest.registration.invitation.find', [$organization, $event])
                ->withErrors(['identifier' => "Ce lien d'invitation n'est plus valable. Retrouvez votre invitation avec votre e-mail ou votre numéro WhatsApp."]);
        }

        return $this->remember($request, $invitee, $organization, $event);
    }

    public function find(Request $request): View
    {
        return view('guest.registration.find-invitation', ['event' => $this->event($request)]);
    }

    public function lookup(FindInvitationRequest $request, string $organization, string $event, IdentifyGuestInvitee $identifyGuestInvitee): RedirectResponse
    {
        $invitee = $identifyGuestInvitee->byEmailOrPhone($this->event($request)->id, (string) $request->validated('identifier'));

        if ($invitee === null) {
            throw ValidationException::withMessages(['identifier' => "Nous ne trouvons pas d'invitation avec cette adresse ou ce numéro. Vérifiez-les, ou contactez l'organisateur."]);
        }

        return $this->remember($request, $invitee, $organization, $event);
    }

    private function remember(Request $request, EventInvitee $invitee, string $organization, string $event): RedirectResponse
    {
        $request->session()->put("guest_invitee.{$invitee->event_id}", ['id' => $invitee->id, 'token' => $invitee->invitation_token]);

        return redirect()->route('guest.registration.start', [$organization, $event]);
    }

    private function event(Request $request): Event
    {
        return $request->attributes->get('guestEvent');
    }
}
