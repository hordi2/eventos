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
    public function handle(string $name, string $email, string $password, string $organizationName): User
    {
        return DB::transaction(function () use ($name, $email, $password, $organizationName): User {
            $user = User::query()->create([
                'name' => $name,
                'email' => $email,
                'password' => $password,
            ]);

            $organization = Organization::query()->create([
                'name' => $organizationName,
                'slug' => Str::slug($organizationName).'-'.Str::lower(Str::random(6)),
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
