<?php

declare(strict_types=1);

namespace App\Domain\Organization\Actions;

use App\Domain\Organization\Models\MembershipRole;
use App\Domain\Organization\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use RuntimeException;

final class AuthenticateViaGoogle
{
    public function handle(SocialiteUser $googleUser): User
    {
        $email = $googleUser->getEmail();

        if ($email === null) {
            throw new RuntimeException('Google n\'a fourni aucune adresse e-mail pour ce compte.');
        }

        $existingUser = User::query()->where('email', $email)->first();

        if ($existingUser !== null) {
            return $existingUser;
        }

        return DB::transaction(function () use ($googleUser, $email): User {
            $name = $googleUser->getName() ?? $googleUser->getNickname() ?? $email;

            $user = User::query()->create([
                'name' => $name,
                'email' => $email,
                'password' => Str::password(32),
            ]);

            // email_verified_at n'est pas mass-assignable (à raison, ça
            // éviterait toute vérification si un attaquant le passait dans un
            // formulaire) : on le marque explicitement après coup, l'adresse
            // ayant déjà été vérifiée par Google.
            $user->markEmailAsVerified();

            $organizationName = __('Organisation de :name', ['name' => $name]);

            $organization = Organization::query()->create([
                'name' => $organizationName,
                'slug' => Str::slug($organizationName).'-'.Str::lower(Str::random(6)),
            ]);

            $user->memberships()->create([
                'organization_id' => $organization->id,
                'role' => MembershipRole::Owner,
            ]);

            return $user;
        });
    }
}
