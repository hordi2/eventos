<?php

declare(strict_types=1);

namespace App\Domain\Organization\Actions;

use App\Domain\Organization\Models\Membership;
use App\Domain\Organization\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * L'agence rend un compte à son client (D10).
 *
 * Le lien se coupe et les accès de l'agence sont retirés ; le client garde
 * tout — ses événements, ses contacts, ses inscriptions, son historique.
 * C'est ce qui distingue un compte confié d'un compte possédé, et ce que
 * demande le cahier des charges (transfert de propriété, persona P3).
 *
 * Les membres du compte qui n'appartiennent pas à l'agence restent en
 * place : ce sont les gens du client.
 */
final class ReleaseClientAccount
{
    public function handle(Organization $agency, Organization $client, User $user): Organization
    {
        Gate::forUser($user)->authorize('manageClients', $agency);

        abort_unless($client->managed_by_organization_id === $agency->id, 404);

        return DB::transaction(function () use ($agency, $client): Organization {
            $agencyUserIds = Membership::query()
                ->where('organization_id', $agency->id)
                ->pluck('user_id');

            Membership::query()
                ->where('organization_id', $client->id)
                ->whereIn('user_id', $agencyUserIds)
                ->delete();

            $client->update(['managed_by_organization_id' => null, 'managed_since' => null]);

            return $client;
        });
    }
}
