<?php

declare(strict_types=1);

namespace App\Domain\Event\Actions;

use App\Domain\Event\InvalidEventTransitionException;
use App\Domain\Event\Models\Event;
use App\Domain\Event\Models\EventStatus;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

final class ArchiveEvent
{
    public function handle(Event $event, User $archiver): Event
    {
        Gate::forUser($archiver)->authorize('archive', $event);

        if ($event->status === EventStatus::Archived) {
            throw InvalidEventTransitionException::cannotArchive($event->status->value);
        }

        $event->update(['status' => EventStatus::Archived]);

        return $event->refresh();
    }
}
