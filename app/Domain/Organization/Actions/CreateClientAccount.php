<?php

declare(strict_types=1);

namespace App\Domain\Organization\Actions;

use App\Domain\Organization\Models\Membership;
use App\Domain\Organization\Models\MembershipRole;
use App\Domain\Organization\Models\Organization;
use App\Domain\Organization\Models\PlanTier;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * Une agence ouvre le compte d'un de ses clients (D10).
 *
 * Le compte créé est une organisation à part entière, cloisonnée comme
 * toutes les autres : l'agence la gère, mais pourra la lui rendre sans que
 * le client perde quoi que ce soit (ReleaseClientAccount).
 *
 * Toujours ouvert sur le palier gratuit, quel que soit celui de l'agence :
 * la facturation consolidée (D10, ManageClientSubscriptionItems) modifie
 * l'abonnement Stripe de l'agence, jamais un effet de bord silencieux d'une
 * simple ouverture de compte — l'agence choisit ensuite explicitement le
 * palier de ce client (ChangeClientPlan, Paramètres → Mes clients).
 *
 * Les membres de l'agence qui ont la main sur le portefeuille entrent
 * d'office dans le nouveau compte : sans cela, l'agence ouvrirait un compte
 * où elle ne pourrait pas travailler.
 */
final class CreateClientAccount
{
    public function handle(Organization $agency, User $creator, string $name): Organization
    {
        Gate::forUser($creator)->authorize('manageClients', $agency);

        return DB::transaction(function () use ($agency, $name): Organization {
            $client = Organization::query()->create([
                'name' => $name,
                'slug' => $this->uniqueSlug($name),
                'managed_by_organization_id' => $agency->id,
                'managed_since' => CarbonImmutable::now(),
                'plan' => PlanTier::Free,
            ]);

            // Ouvrir un compte client fait de vous une agence : le
            // portefeuille apparaît alors dans vos paramètres.
            if (! $agency->is_agency) {
                $agency->update(['is_agency' => true]);
            }

            $agency->loadMissing('memberships');

            foreach ($agency->memberships as $membership) {
                if (! in_array($membership->role, [MembershipRole::Owner, MembershipRole::Admin], true)) {
                    continue;
                }

                Membership::query()->create([
                    'organization_id' => $client->id,
                    'user_id' => $membership->user_id,
                    // Jamais propriétaire : le compte appartient au client,
                    // l'agence n'y est qu'administratrice.
                    'role' => MembershipRole::Admin,
                ]);
            }

            return $client;
        });
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'client';
        $slug = $base;
        $suffix = 2;

        // withTrashed : un compte fermé garde son slug en base, l'unicité
        // doit en tenir compte.
        while (Organization::withTrashed()->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
