<?php

declare(strict_types=1);

namespace App\Http\Controllers\Guest;

use App\Domain\Event\Models\Event;
use App\Http\Controllers\Controller;
use App\Support\GuestList\IdentifyGuestInvitee;
use App\Support\Invitation\ResolveInviteeQr;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Code QR personnel d'un invité, servi en image (D1). Une balise <img>
 * suffit à l'afficher : c'est ce qui permet à une invitation hébergée
 * ailleurs — site d'un prestataire, page produite avec un autre outil — de
 * montrer le code de chaque invité sans rien connaître d'Itaza.
 *
 * L'image ne dit rien de l'invité : elle porte son code d'entrée s'il est
 * inscrit, sinon l'adresse de son invitation.
 */
final class InvitationQrController extends Controller
{
    public function __invoke(Request $request, string $organization, string $event, string $invitationToken, IdentifyGuestInvitee $identifyGuestInvitee, ResolveInviteeQr $resolveInviteeQr): Response
    {
        $eventModel = $this->event($request);
        $invitee = $identifyGuestInvitee->byToken($eventModel->id, $invitationToken);
        abort_if($invitee === null, 404);

        return response($resolveInviteeQr->png($eventModel, $invitee), 200, [
            'Content-Type' => 'image/png',
            // Un code d'entrée change quand l'invité s'inscrit : court, le
            // cache évite les rendus inutiles sans figer une image périmée.
            'Cache-Control' => 'public, max-age=300',
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
