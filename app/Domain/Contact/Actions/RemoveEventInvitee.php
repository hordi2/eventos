<?php

declare(strict_types=1);

namespace App\Domain\Contact\Actions;

use App\Domain\Contact\Models\EventInvitee;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * Retire une personne de la liste d'invités (suppression logique, règle
 * 4.5). Son contact et ses éventuelles réponses restent intacts.
 */
final class RemoveEventInvitee
{
    public function handle(EventInvitee $invitee, User $user): void
    {
        $invitee->loadMissing('contact.organization');
        Gate::forUser($user)->authorize('updateGuests', $invitee->contact->organization);

        $invitee->delete();
    }
}
