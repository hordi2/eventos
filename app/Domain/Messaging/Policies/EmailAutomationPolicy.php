<?php

declare(strict_types=1);

namespace App\Domain\Messaging\Policies;

use App\Domain\Messaging\Models\EmailAutomation;
use App\Domain\Organization\Models\Organization;
use App\Models\User;

final class EmailAutomationPolicy
{
    public function viewAny(User $user, Organization $organization): bool
    {
        return $user->can('sendCommunications', $organization);
    }

    public function create(User $user, Organization $organization): bool
    {
        return $user->can('sendCommunications', $organization);
    }

    public function cancel(User $user, EmailAutomation $emailAutomation): bool
    {
        return $user->can('sendCommunications', $emailAutomation->organization);
    }
}
