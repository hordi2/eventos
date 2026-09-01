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

        // À faire dès que le module Inscriptions existera (T-030) : si ce
        // sous-événement a encore des inscriptions actives, refuser la
        // suppression ou exiger une confirmation explicite plutôt que de
        // supprimer silencieusement (critère d'acceptation M1.3). Impossible
        // à vérifier avant que le modèle Registration n'existe. La
        // suppression logique ci-dessous reste réversible en attendant
        // (règle 4.5 du CLAUDE.md).

        $event->delete();
    }
}
