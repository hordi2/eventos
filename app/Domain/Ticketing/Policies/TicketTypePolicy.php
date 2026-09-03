<?php

declare(strict_types=1);

namespace App\Domain\Ticketing\Policies;

use App\Domain\Organization\Models\Organization;
use App\Domain\Ticketing\Models\TicketType;
use App\Models\User;

final class TicketTypePolicy
{
    public function viewAny(User $user, Organization $organization): bool
    {
        return $user->can('manageTicketing', $organization);
    }

    public function create(User $user, Organization $organization): bool
    {
        return $user->can('manageTicketing', $organization);
    }

    public function update(User $user, TicketType $ticketType): bool
    {
        return $user->can('manageTicketing', $ticketType->organization);
    }

    public function delete(User $user, TicketType $ticketType): bool
    {
        return $user->can('manageTicketing', $ticketType->organization);
    }
}
