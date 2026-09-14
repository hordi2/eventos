<?php

declare(strict_types=1);

namespace App\Support\Collaboration;

use App\Domain\Event\Models\Event;
use App\Domain\Organization\Models\Collaborator;
use App\Domain\Organization\Models\CollaboratorEventPermission;
use App\Domain\Organization\Models\CollaboratorPermission;

/**
 * Traverse Domain/Organization et Domain/Event, d'où sa place hors des deux
 * modules : les titres d'événements affichés dans l'e-mail d'invitation et
 * sur la page d'acceptation.
 */
final class DescribeCollaboratorEvents
{
    /**
     * @return list<array{title: string, permission: string}>
     */
    public function handle(Collaborator $collaborator): array
    {
        $permissions = $collaborator->eventPermissions()
            ->where('permission', '!=', CollaboratorPermission::None->value)
            ->get()
            ->keyBy('event_id');

        return Event::query()
            ->whereIn('id', $permissions->keys()->all())
            ->orderBy('start_at')
            ->get(['id', 'title'])
            ->map(function (Event $event) use ($permissions): array {
                /** @var CollaboratorEventPermission $permission */
                $permission = $permissions->get($event->id);

                return ['title' => $event->title, 'permission' => $permission->permission->label()];
            })
            ->values()
            ->all();
    }
}
