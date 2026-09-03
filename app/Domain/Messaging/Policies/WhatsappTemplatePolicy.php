<?php

declare(strict_types=1);

namespace App\Domain\Messaging\Policies;

use App\Domain\Messaging\Models\WhatsappTemplate;
use App\Domain\Organization\Models\Organization;
use App\Models\User;

final class WhatsappTemplatePolicy
{
    public function viewAny(User $user, Organization $organization): bool
    {
        return $user->can('sendCommunications', $organization);
    }

    public function create(User $user, Organization $organization): bool
    {
        return $user->can('sendCommunications', $organization);
    }

    public function update(User $user, WhatsappTemplate $whatsappTemplate): bool
    {
        return $user->can('sendCommunications', $whatsappTemplate->organization);
    }

    public function delete(User $user, WhatsappTemplate $whatsappTemplate): bool
    {
        return $user->can('sendCommunications', $whatsappTemplate->organization);
    }
}
