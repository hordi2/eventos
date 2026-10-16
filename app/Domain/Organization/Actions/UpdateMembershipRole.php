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
 * qui journalise déjà "membership.updated" avec l'ancien et le nouveau
 * rôle (voir AuditableTraitTest) — y ajouter une entrée reviendrait à la
 * dupliquer.
 */
final class UpdateMembershipRole
{
    public function handle(Membership $membership, User $actor, MembershipRole $newRole): Membership
    {
        $organization = Organization::query()->findOrFail($membership->organization_id);
        Gate::forUser($actor)->authorize('inviteMembers', $organization);

        if ($newRole === MembershipRole::Owner || $membership->role === MembershipRole::Owner) {
            throw InvalidMembershipActionException::cannotSetOwnerRole();
        }

        $membership->update(['role' => $newRole]);

        return $membership->fresh();
    }
}
