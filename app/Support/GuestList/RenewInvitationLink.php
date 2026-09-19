<?php

declare(strict_types=1);

namespace App\Support\GuestList;

use App\Domain\Contact\Models\EventInvitee;
use App\Domain\Form\Models\RegistrationDraft;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * Remplace le lien personnel d'un invité, par exemple transmis à la mauvaise
 * personne : l'ancien cesse aussitôt d'ouvrir l'invitation, y compris dans
 * un navigateur qui l'avait déjà suivi (ResolveGuestEvent compare le jeton),
 * et les réponses commencées avec lui mais pas envoyées en sont détachées.
 * L'invité peut toujours se retrouver par son e-mail ou son numéro WhatsApp.
 *
 * Hors des modules (App\Support) : touche la liste (Domain/Contact) et les
 * brouillons (Domain/Form).
 */
final class RenewInvitationLink
{
    public function handle(EventInvitee $invitee, User $user): EventInvitee
    {
        $invitee->loadMissing('contact.organization');
        Gate::forUser($user)->authorize('updateGuests', $invitee->contact->organization);

        DB::transaction(function () use ($invitee): void {
            $invitee->update(['invitation_token' => Str::random(40)]);

            RegistrationDraft::query()
                ->where('event_invitee_id', $invitee->id)
                ->whereNull('submitted_at')
                ->update(['event_invitee_id' => null]);
        });

        return $invitee->refresh();
    }
}
