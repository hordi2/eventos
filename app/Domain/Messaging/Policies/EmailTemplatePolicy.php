<?php

declare(strict_types=1);

namespace App\Domain\Messaging\Policies;

use App\Domain\Messaging\Models\EmailTemplate;
use App\Domain\Organization\Models\Organization;
use App\Models\User;

final class EmailTemplatePolicy
{
    public function viewAny(User $user, Organization $organization): bool
    {
        return $user->can('sendCommunications', $organization);
    }

    public function create(User $user, Organization $organization): bool
    {
        return $user->can('sendCommunications', $organization);
    }

    public function update(User $user, EmailTemplate $emailTemplate): bool
    {
        return $user->can('sendCommunications', $emailTemplate->organization);
    }

    public function delete(User $user, EmailTemplate $emailTemplate): bool
    {
        return $user->can('sendCommunications', $emailTemplate->organization);
    }
}
