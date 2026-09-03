<?php

declare(strict_types=1);

namespace App\Domain\Contact\Policies;

use App\Domain\Contact\Models\Tag;
use App\Domain\Organization\Models\Organization;
use App\Models\User;

final class TagPolicy
{
    public function viewAny(User $user, Organization $organization): bool
    {
        return $user->can('viewGuests', $organization);
    }

    public function create(User $user, Organization $organization): bool
    {
        return $user->can('updateGuests', $organization);
    }

    public function update(User $user, Tag $tag): bool
    {
        return $user->can('updateGuests', $tag->organization);
    }

    public function delete(User $user, Tag $tag): bool
    {
        return $user->can('updateGuests', $tag->organization);
    }
}
