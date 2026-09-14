<?php

declare(strict_types=1);

namespace App\Domain\Organization\Services;

use App\Domain\Organization\Models\CollaboratorEventPermission;
use App\Domain\Organization\Models\CollaboratorPermission;
use App\Domain\Organization\Models\Membership;
use App\Domain\Organization\Models\MembershipRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Lit les accès d'un collaborateur. Une invitation en attente ou retirée
 * (collaborateur supprimé logiquement) ne donne jamais accès : seules
 * comptent les invitations acceptées par ce compte.
 *
 * Les requêtes passent par le scope organisation : l'organisation courante
 * doit être celle demandée, sans quoi rien n'est trouvé (refus par défaut).
 */
final class CollaboratorAccess
{
    public function isCollaborator(User $user, int $organizationId): bool
    {
        return Membership::query()
            ->where('user_id', $user->id)
            ->where('organization_id', $organizationId)
            ->where('role', MembershipRole::Collaborator)
            ->exists();
    }

    public function permissionFor(User $user, int $organizationId, int $eventId): CollaboratorPermission
    {
        $row = $this->acceptedPermissions($user, $organizationId)
            ->where('event_id', $eventId)
            ->first();

        return $row === null ? CollaboratorPermission::None : $row->permission;
    }

    /**
     * @return array<int, CollaboratorPermission> permission par id d'événement, sans les « Aucun accès »
     */
    public function sharedEvents(User $user, int $organizationId): array
    {
        return $this->acceptedPermissions($user, $organizationId)
            ->where('permission', '!=', CollaboratorPermission::None->value)
            ->get()
            ->mapWithKeys(fn (CollaboratorEventPermission $row): array => [$row->event_id => $row->permission])
            ->all();
    }

    /**
     * @return Builder<CollaboratorEventPermission>
     */
    private function acceptedPermissions(User $user, int $organizationId): Builder
    {
        return CollaboratorEventPermission::query()
            ->where('organization_id', $organizationId)
            ->whereHas('collaborator', fn (Builder $query): Builder => $query
                ->where('user_id', $user->id)
                ->whereNotNull('accepted_at'));
    }
}
