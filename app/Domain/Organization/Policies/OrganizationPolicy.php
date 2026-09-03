<?php

declare(strict_types=1);

namespace App\Domain\Organization\Policies;

use App\Domain\Organization\Models\Membership;
use App\Domain\Organization\Models\MembershipRole;
use App\Domain\Organization\Models\Organization;
use App\Models\User;

/**
 * Matrice de rôles M0.3 du cahier des charges. Les capacités portant sur des
 * ressources qui n'existent pas encore (événements, invités, billets...)
 * sont définies dès maintenant au niveau de l'organisation : les modules
 * concernés (T-010+) les consommeront via $user->can('...', $organization)
 * plutôt que de réinventer une matrice de rôles à chaque fois.
 *
 * Le rôle spécifique par événement mentionné en M0.2 n'est pas implémenté
 * ici : sans modèle Event, il n'y a rien à surcharger. À construire quand
 * T-010 existera.
 */
final class OrganizationPolicy
{
    /**
     * @var array<string, list<MembershipRole>>
     */
    private const ABILITIES = [
        'manageBilling' => [MembershipRole::Owner],
        'inviteMembers' => [MembershipRole::Owner, MembershipRole::Admin],
        'createEvents' => [MembershipRole::Owner, MembershipRole::Admin],
        'deleteEvents' => [MembershipRole::Owner, MembershipRole::Admin],
        'updateEvents' => [MembershipRole::Owner, MembershipRole::Admin, MembershipRole::Editor],
        'viewGuests' => [
            MembershipRole::Owner,
            MembershipRole::Admin,
            MembershipRole::Editor,
            MembershipRole::DoorStaff,
            MembershipRole::Viewer,
        ],
        'updateGuests' => [MembershipRole::Owner, MembershipRole::Admin, MembershipRole::Editor],
        'sendCommunications' => [MembershipRole::Owner, MembershipRole::Admin, MembershipRole::Editor],
        'checkIn' => [MembershipRole::Owner, MembershipRole::Admin, MembershipRole::Editor, MembershipRole::DoorStaff],
        'manageTicketing' => [MembershipRole::Owner, MembershipRole::Admin, MembershipRole::Editor],
        'refundTickets' => [MembershipRole::Owner, MembershipRole::Admin],
        'viewAuditLog' => [MembershipRole::Owner, MembershipRole::Admin],
    ];

    /**
     * Abilities marquées ⚙️ dans la matrice : l'éditeur y a accès uniquement
     * si le propriétaire l'a explicitement activé pour son organisation.
     * Owner et Admin y ont toujours accès.
     *
     * @var list<string>
     */
    private const EDITOR_CONFIGURABLE_ABILITIES = ['viewFinancials', 'exportData'];

    public function manageBilling(User $user, Organization $organization): bool
    {
        return $this->check($user, $organization, __FUNCTION__);
    }

    public function inviteMembers(User $user, Organization $organization): bool
    {
        return $this->check($user, $organization, __FUNCTION__);
    }

    public function createEvents(User $user, Organization $organization): bool
    {
        return $this->check($user, $organization, __FUNCTION__);
    }

    public function deleteEvents(User $user, Organization $organization): bool
    {
        return $this->check($user, $organization, __FUNCTION__);
    }

    public function updateEvents(User $user, Organization $organization): bool
    {
        return $this->check($user, $organization, __FUNCTION__);
    }

    public function viewGuests(User $user, Organization $organization): bool
    {
        return $this->check($user, $organization, __FUNCTION__);
    }

    public function updateGuests(User $user, Organization $organization): bool
    {
        return $this->check($user, $organization, __FUNCTION__);
    }

    public function sendCommunications(User $user, Organization $organization): bool
    {
        return $this->check($user, $organization, __FUNCTION__);
    }

    public function checkIn(User $user, Organization $organization): bool
    {
        return $this->check($user, $organization, __FUNCTION__);
    }

    public function manageTicketing(User $user, Organization $organization): bool
    {
        return $this->check($user, $organization, __FUNCTION__);
    }

    public function viewFinancials(User $user, Organization $organization): bool
    {
        return $this->check($user, $organization, __FUNCTION__);
    }

    public function exportData(User $user, Organization $organization): bool
    {
        return $this->check($user, $organization, __FUNCTION__);
    }

    public function refundTickets(User $user, Organization $organization): bool
    {
        return $this->check($user, $organization, __FUNCTION__);
    }

    public function viewAuditLog(User $user, Organization $organization): bool
    {
        return $this->check($user, $organization, __FUNCTION__);
    }

    private function check(User $user, Organization $organization, string $ability): bool
    {
        // ->first()?->role (plutôt que ->value('role')) pour être certain de
        // repasser par le cast Eloquent vers l'enum MembershipRole.
        $role = Membership::query()
            ->where('user_id', $user->id)
            ->where('organization_id', $organization->id)
            ->first()
            ?->role;

        if ($role === null) {
            return false;
        }

        if (in_array($ability, self::EDITOR_CONFIGURABLE_ABILITIES, true)) {
            if ($role === MembershipRole::Owner || $role === MembershipRole::Admin) {
                return true;
            }

            if ($role === MembershipRole::Editor) {
                // Cast défensif : create() ne relit pas les valeurs par
                // défaut de la base pour les colonnes non précisées, donc un
                // modèle fraîchement créé sans le champ explicite aurait cet
                // attribut à null en mémoire malgré le défaut false en base.
                return (bool) $organization->allow_editor_financial_access;
            }

            return false;
        }

        return in_array($role, self::ABILITIES[$ability] ?? [], true);
    }
}
