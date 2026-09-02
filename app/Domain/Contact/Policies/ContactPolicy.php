<?php

declare(strict_types=1);

namespace App\Domain\Contact\Policies;

use App\Domain\Contact\Models\Contact;
use App\Domain\Organization\Models\Organization;
use App\Models\User;

final class ContactPolicy
{
    public function viewAny(User $user, Organization $organization): bool
    {
        return $user->can('viewGuests', $organization);
    }

    public function view(User $user, Contact $contact): bool
    {
        return $user->can('viewGuests', $contact->organization);
    }

    public function create(User $user, Organization $organization): bool
    {
        return $user->can('updateGuests', $organization);
    }

    public function update(User $user, Contact $contact): bool
    {
        return $user->can('updateGuests', $contact->organization);
    }

    public function delete(User $user, Contact $contact): bool
    {
        return $user->can('updateGuests', $contact->organization);
    }
}
