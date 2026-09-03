<?php

declare(strict_types=1);

namespace App\Domain\Messaging\Policies;

use App\Domain\Messaging\Models\MessageAutomation;
use App\Domain\Organization\Models\Organization;
use App\Models\User;

final class MessageAutomationPolicy
{
    public function viewAny(User $user, Organization $organization): bool
    {
        return $user->can('sendCommunications', $organization);
    }

    public function create(User $user, Organization $organization): bool
    {
        return $user->can('sendCommunications', $organization);
    }

    public function cancel(User $user, MessageAutomation $messageAutomation): bool
    {
        return $user->can('sendCommunications', $messageAutomation->organization);
    }
}
