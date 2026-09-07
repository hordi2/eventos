<?php

declare(strict_types=1);

namespace App\Support\Gdpr;

use App\Domain\Organization\Models\MembershipRole;
use App\Models\User;

/**
 * Suppression de compte demandée par l'utilisateur lui-même, depuis la page
 * Paramètres. Même principe que AnonymizeContact (T-075) : une
 * anonymisation, jamais un DELETE physique (règle 4.5 du CLAUDE.md) — le
 * compte est retiré de la connexion mais les lignes qu'il a produites
 * ailleurs (audit_logs.causer, memberships historiques...) restent
 * intactes.
 *
 * Bloquée tant que l'utilisateur est le seul membre au rôle Owner d'une
 * organisation non supprimée : au contraire d'un Contact, un User peut être
 * seul responsable légal d'événements et de données réelles — le laisser
 * partir sans successeur laisserait l'organisation orpheline.
 */
final class AnonymizeUser
{
    public function handle(User $user): void
    {
        if ($this->isSoleOwnerOfAnOrganization($user)) {
            throw SoleOrganizationOwnerException::soleOwner();
        }

        $user->forceFill([
            'name' => 'Compte supprimé',
            'email' => "compte-supprime-{$user->id}@itaza.invalid",
            'password' => (string) str()->random(40),
            'remember_token' => null,
            'email_verified_at' => null,
        ])->save();

        $user->tokens()->delete();
        $user->delete();
    }

    public function isSoleOwnerOfAnOrganization(User $user): bool
    {
        return $user->memberships()
            ->where('role', MembershipRole::Owner)
            ->whereHas('organization')
            ->get()
            ->contains(function ($membership): bool {
                return $membership->organization->memberships()
                    ->where('role', MembershipRole::Owner)
                    ->count() === 1;
            });
    }
}
