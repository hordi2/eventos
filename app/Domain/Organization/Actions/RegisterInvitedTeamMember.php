<?php

declare(strict_types=1);

namespace App\Domain\Organization\Actions;

use App\Domain\Organization\Models\MembershipInvitation;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Création de compte depuis une invitation : contrairement à RegisterUser,
 * aucune organisation n'est créée — la personne arrive directement dans
 * celle qui l'invite, avec le rôle choisi (même décision produit que
 * RegisterInvitedCollaborator).
 */
final class RegisterInvitedTeamMember
{
    public function __construct(
        private readonly AcceptTeamMemberInvitation $acceptTeamMemberInvitation,
    ) {}

    public function handle(MembershipInvitation $invitation, string $name, string $password): User
    {
        return DB::transaction(function () use ($invitation, $name, $password): User {
            $user = User::query()->create([
                'name' => $name,
                'email' => $invitation->email,
                'password' => $password,
            ]);

            $this->acceptTeamMemberInvitation->handle($invitation, $user);

            return $user;
        });
    }
}
