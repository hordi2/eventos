<?php

declare(strict_types=1);

namespace App\Domain\Organization\Actions;

use App\Domain\Organization\Models\Collaborator;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Création de compte depuis une invitation : contrairement à RegisterUser,
 * aucune organisation n'est créée (décision produit — la personne arrive
 * directement sur les événements partagés) et aucun e-mail de vérification
 * n'est envoyé, l'adresse étant déjà prouvée par le lien d'invitation.
 */
final class RegisterInvitedCollaborator
{
    public function __construct(
        private readonly AcceptCollaboratorInvitation $acceptCollaboratorInvitation,
    ) {}

    public function handle(Collaborator $collaborator, string $name, string $password): User
    {
        return DB::transaction(function () use ($collaborator, $name, $password): User {
            $user = User::query()->create([
                'name' => $name,
                'email' => $collaborator->email,
                'password' => $password,
            ]);

            $this->acceptCollaboratorInvitation->handle($collaborator, $user);

            return $user;
        });
    }
}
