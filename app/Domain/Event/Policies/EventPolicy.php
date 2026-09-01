<?php

declare(strict_types=1);

namespace App\Domain\Event\Policies;

use App\Domain\Event\Models\Event;
use App\Domain\Organization\Models\Organization;
use App\Models\User;

/**
 * S'appuie sur les capacités déjà définies dans OrganizationPolicy (T-004)
 * plutôt que de dupliquer la matrice de rôles. Publier et archiver sont
 * traités comme des modifications (updateEvents) : ce sont des changements
 * de statut, pas des créations ni des suppressions de ligne.
 */
final class EventPolicy
{
    public function create(User $user, Organization $organization): bool
    {
        return $user->can('createEvents', $organization);
    }

    public function update(User $user, Event $event): bool
    {
        return $user->can('updateEvents', $event->organization);
    }

    public function delete(User $user, Event $event): bool
    {
        return $user->can('deleteEvents', $event->organization);
    }

    public function publish(User $user, Event $event): bool
    {
        return $user->can('updateEvents', $event->organization);
    }

    public function archive(User $user, Event $event): bool
    {
        return $user->can('updateEvents', $event->organization);
    }
}
