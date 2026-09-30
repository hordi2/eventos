<?php

declare(strict_types=1);

namespace App\Domain\Event\Actions;

use App\Domain\Event\Models\Event;
use App\Domain\Event\Models\Speaker;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * Retire un intervenant du programme. Suppression logique (règle 4.5) :
 * sa fiche disparaît de la page publique, son passage reste traçable.
 */
final class DeleteSpeaker
{
    public function handle(Speaker $speaker, Event $event, User $editor): void
    {
        Gate::forUser($editor)->authorize('update', $event);

        $speaker->sessions()->detach();
        $speaker->delete();
    }
}
