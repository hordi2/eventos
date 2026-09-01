<?php

declare(strict_types=1);

namespace App\Domain\Event\Actions;

use App\Domain\Event\InvalidEventTransitionException;
use App\Domain\Event\Models\Event;
use App\Domain\Event\Models\EventStatus;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

final class PublishEvent
{
    public function handle(Event $event, User $publisher): Event
    {
        Gate::forUser($publisher)->authorize('publish', $event);

        if ($event->status !== EventStatus::Draft) {
            throw InvalidEventTransitionException::cannotPublish($event->status->value);
        }

        // À ajouter dès que le module Formulaires existera (T-020) :
        // refuser la publication si l'événement n'a aucun formulaire actif
        // (critère du CDC, non implémentable avant que Form n'existe).

        $event->update(['status' => EventStatus::Published]);

        return $event->refresh();
    }
}
