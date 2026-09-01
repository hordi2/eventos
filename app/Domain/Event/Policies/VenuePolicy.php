<?php

declare(strict_types=1);

namespace App\Domain\Event\Policies;

use App\Domain\Event\Models\Venue;
use App\Domain\Organization\Models\Organization;
use App\Models\User;

final class VenuePolicy
{
    public function create(User $user, Organization $organization): bool
    {
        return $user->can('createEvents', $organization);
    }

    public function update(User $user, Venue $venue): bool
    {
        return $user->can('updateEvents', $venue->organization);
    }

    public function delete(User $user, Venue $venue): bool
    {
        return $user->can('deleteEvents', $venue->organization);
    }
}
