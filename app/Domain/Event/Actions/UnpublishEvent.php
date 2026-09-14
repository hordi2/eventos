<?php

declare(strict_types=1);

namespace App\Domain\Event\Actions;

use App\Domain\Event\InvalidEventTransitionException;
use App\Domain\Event\Models\Event;
use App\Domain\Event\Models\EventStatus;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * Remet un événement publié en brouillon (« Inédit ») — décision produit :
 * la page publique et les inscriptions se ferment (ResolveGuestEvent), les
 * inscriptions déjà reçues restent rattachées à l'événement. Un événement
 * archivé ne revient jamais en brouillon.
 */
final class UnpublishEvent
{
    public function handle(Event $event, User $actor): Event
    {
        Gate::forUser($actor)->authorize('unpublish', $event);

        if ($event->status !== EventStatus::Published) {
            throw InvalidEventTransitionException::cannotUnpublish($event->status->value);
        }

        $event->update(['status' => EventStatus::Draft]);

        return $event->refresh();
    }
}
