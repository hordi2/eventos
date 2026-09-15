<?php

declare(strict_types=1);

namespace App\Domain\Event\Actions;

use App\Domain\Event\CannotDeleteEventException;
use App\Domain\Event\Models\Event;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

final class DeleteEvent
{
    public function handle(Event $event, User $deleter): void
    {
        Gate::forUser($deleter)->authorize('delete', $event);

        if ($event->subEvents()->exists()) {
            throw CannotDeleteEventException::hasSubEvents();
        }

        // Les inscriptions actives d'un sous-événement sont vérifiées avant
        // cette action par App\Support\Events\DeleteSubEvent (critère M1.3) :
        // Domain/Event ne lit jamais les modèles de Domain/Form (section 3 du
        // CLAUDE.md). La suppression reste logique et réversible (règle 4.5).

        $event->delete();
    }
}
