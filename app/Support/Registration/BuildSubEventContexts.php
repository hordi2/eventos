<?php

declare(strict_types=1);

namespace App\Support\Registration;

use App\Domain\Event\Models\Event;
use App\Domain\Form\Data\SubEventContext;

/**
 * Traduit les événements secondaires d'un événement en valeurs simples
 * pour Domain/Form, qui ne référence jamais Domain/Event (section 3 du
 * CLAUDE.md).
 */
final class BuildSubEventContexts
{
    /**
     * @return list<SubEventContext>
     */
    public function handle(Event $event): array
    {
        return $event->subEvents()->orderBy('start_at')->get()
            ->map(fn (Event $subEvent): SubEventContext => new SubEventContext(
                eventId: $subEvent->id,
                title: $subEvent->title,
                capacity: $subEvent->capacity,
                allowWaitlist: $subEvent->allow_waitlist,
                startAt: $subEvent->start_at,
                endAt: $subEvent->end_at,
            ))
            ->values()
            ->all();
    }
}
