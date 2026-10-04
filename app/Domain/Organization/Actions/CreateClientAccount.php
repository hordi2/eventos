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
                // Le compte client hérite du plan de l'agence : c'est elle
                // qui paie pour tout son portefeuille. Repli défensif :
                // create() ne relit pas les valeurs par défaut de la base,
                // une agence fraîchement créée peut porter un plan nul en
                // mémoire malgré son défaut en base.
                'plan' => $agency->plan ?? PlanTier::Free,
            ]);

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
