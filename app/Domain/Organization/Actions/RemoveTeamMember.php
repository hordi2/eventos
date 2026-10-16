<?php

declare(strict_types=1);

namespace App\Domain\Organization\Actions;

use App\Domain\Organization\InvalidMembershipActionException;
use App\Domain\Organization\Models\Membership;
use App\Domain\Organization\Models\MembershipRole;
use App\Domain\Organization\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * Pas d'appel à RecordAuditLog ici : Membership porte le trait Auditable,
 * qui journalise déjà "membership.deleted" (voir AuditableTraitTest) — y
 * ajouter une entrée reviendrait à la dupliquer.
 */
final class RemoveTeamMember
{
    public function handle(Membership $membership, User $actor): void
    {
        $organization = Organization::query()->findOrFail($membership->organization_id);
        Gate::forUser($actor)->authorize('inviteMembers', $organization);

        if ($membership->role === MembershipRole::Owner) {
            throw InvalidMembershipActionException::cannotRemoveOwner();
        }

        $membership->delete();
    }
}
