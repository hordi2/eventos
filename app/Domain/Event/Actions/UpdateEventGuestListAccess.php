<?php

declare(strict_types=1);

namespace App\Domain\Event\Actions;

use App\Domain\Event\Models\Event;
use App\Domain\Event\Models\EventAccessMode;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * Réserve l'événement à sa liste d'invités (liste fermée), ou le rouvre à
 * tous. Réglé depuis la liste d'invités ; un événement protégé par mot de
 * passe qu'on réserve à sa liste perd son mot de passe, les deux accès ne se
 * cumulant pas.
 */
final class UpdateEventGuestListAccess
{
    public function handle(Event $event, User $editor, bool $closed): Event
    {
        Gate::forUser($editor)->authorize('update', $event);

        $event->update(['access_mode' => $closed ? EventAccessMode::ClosedList : EventAccessMode::Public]);

        return $event->refresh();
    }
}
