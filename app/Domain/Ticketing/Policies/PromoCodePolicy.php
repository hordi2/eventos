<?php

declare(strict_types=1);

namespace App\Domain\Ticketing\Policies;

use App\Domain\Organization\Models\Organization;
use App\Domain\Ticketing\Models\PromoCode;
use App\Models\User;

final class PromoCodePolicy
{
    public function viewAny(User $user, Organization $organization): bool
    {
        return $user->can('manageTicketing', $organization);
    }

    public function create(User $user, Organization $organization): bool
    {
        return $user->can('manageTicketing', $organization);
    }

    public function update(User $user, PromoCode $promoCode): bool
    {
        return $user->can('manageTicketing', $promoCode->organization);
    }

    public function delete(User $user, PromoCode $promoCode): bool
    {
        return $user->can('manageTicketing', $promoCode->organization);
    }
}
