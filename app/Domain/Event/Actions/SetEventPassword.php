<?php

declare(strict_types=1);

namespace App\Domain\Event\Actions;

use App\Domain\Event\Models\Event;
use App\Domain\Event\Models\EventAccessMode;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;

/**
 * Protège l'événement par un mot de passe, ou retire cette protection. Même
 * règle que la liste fermée (UpdateEventGuestListAccess) : les deux accès ne
 * se cumulant pas, poser un mot de passe réserve l'accès au mot de passe
 * seul, même si l'événement était jusque-là réservé à sa liste d'invités.
 */
final class SetEventPassword
{
    public function handle(Event $event, User $editor, ?string $password): Event
    {
        Gate::forUser($editor)->authorize('update', $event);

        $event->update($password !== null
            ? ['password_hash' => Hash::make($password), 'access_mode' => EventAccessMode::Password]
            : ['password_hash' => null, 'access_mode' => EventAccessMode::Public]);

        return $event->refresh();
    }
}
