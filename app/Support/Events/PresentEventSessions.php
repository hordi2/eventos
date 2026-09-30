<?php

declare(strict_types=1);

namespace App\Support\Events;

use App\Domain\Event\Models\Event;
use App\Domain\Event\Models\Speaker;

/**
 * Programme d'un événement (D6) : ses sessions, dans l'ordre, avec leur
 * salle et leurs intervenants. Les heures s'affichent dans le fuseau de
 * l'événement (règle 4.3).
 */
final class PresentEventSessions
{
    /**
     * @return list<array{id: int, title: string, room: ?string, day: string, time: string, startAt: string, speakers: list<string>}>
     */
    public function handle(Event $event): array
    {
        return $event->subEvents()
            ->with('speakers')
            ->orderBy('start_at')
            ->get()
            ->map(function (Event $session) use ($event): array {
                $start = $session->start_at->setTimezone($event->timezone);
                $end = $session->end_at->setTimezone($event->timezone);

                return [
                    'id' => $session->id,
                    'title' => $session->title,
                    'room' => $session->room,
                    'day' => $start->translatedFormat('l j F'),
                    'time' => $start->format('H\hi').' – '.$end->format('H\hi'),
                    'startAt' => $session->start_at->toIso8601String(),
                    'speakers' => $session->speakers->map(fn (Speaker $speaker): string => $speaker->name)->values()->all(),
                ];
            })
            ->values()
            ->all();
    }
}
