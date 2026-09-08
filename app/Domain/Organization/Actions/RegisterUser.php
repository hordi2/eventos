<?php

declare(strict_types=1);

namespace App\Domain\Organization\Actions;

use App\Domain\Organization\Models\MembershipRole;
use App\Domain\Organization\Models\Organization;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class RegisterUser
{
    public function handle(string $name, string $email, string $password, string $organizationName, ?string $referralCode = null): User
    {
        return DB::transaction(function () use ($name, $email, $password, $organizationName, $referralCode): User {
            $user = User::query()->create([
                'name' => $name,
                'email' => $email,
                'password' => $password,
            ]);

            $referrer = $referralCode !== null
                ? Organization::query()->where('referral_code', $referralCode)->first()
                : null;

            $organization = Organization::query()->create([
                'name' => $organizationName,
                'slug' => Str::slug($organizationName).'-'.Str::lower(Str::random(6)),
                // Refer-a-Friend (Paramètres → Refer-a-Friend) : code propre à
                // chaque organisation, généré dès la création — jamais
                // partagé avec l'organisation parraine si le code fourni ne
                // correspond à personne (parrainage silencieusement ignoré).
                'referral_code' => Str::lower(Str::random(8)),
                'referred_by_organization_id' => $referrer?->id,
            ]);

            $user->memberships()->create([
                'organization_id' => $organization->id,
                'role' => MembershipRole::Owner,
            ]);

            event(new Registered($user));

            return $user;
        });
    }
}
