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

        // Le formulaire publié exigé par le CDC (M1.2) est vérifié avant cette
        // action par App\Support\Events\PublishEventWithForm : Domain/Event
        // ne lit jamais les modèles de Domain/Form (section 3 du CLAUDE.md).

        $event->update(['status' => EventStatus::Published]);

        return $event->refresh();
    }
}
