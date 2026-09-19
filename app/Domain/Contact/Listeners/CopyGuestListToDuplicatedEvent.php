<?php

declare(strict_types=1);

namespace App\Domain\Contact\Listeners;

use App\Domain\Contact\Models\EventInvitee;
use App\Domain\Event\Data\EventDuplicationPart;
use App\Domain\Event\Events\EventDuplicated;
use Illuminate\Support\Str;

/**
 * Recopie la liste d'invités d'un événement dupliqué : mêmes contacts,
 * mêmes groupes et accompagnants permis, mais un nouveau lien personnel
 * pour chacun — celui de l'original ouvre l'ancien événement. Ni réponse ni
 * historique d'envoi ne suivent. Un contact effacé (RGPD) n'est pas repris.
 */
final class CopyGuestListToDuplicatedEvent
{
    public function handle(EventDuplicated $duplication): void
    {
        if (! $duplication->includes(EventDuplicationPart::GuestList)) {
            return;
        }

        foreach ($duplication->eventIdMap as $sourceEventId => $copyEventId) {
            $invitees = EventInvitee::query()->where('event_id', $sourceEventId)->whereHas('contact')->orderBy('id')->get();

            foreach ($invitees as $invitee) {
                EventInvitee::query()->create([
                    'organization_id' => $invitee->organization_id,
                    'event_id' => $copyEventId,
                    'contact_id' => $invitee->contact_id,
                    'invitation_token' => Str::random(40),
                    'group_key' => $invitee->group_key,
                    'companions_allowed' => $invitee->companions_allowed,
                    'cc_email' => $invitee->cc_email,
                ]);
            }
        }
    }
}
