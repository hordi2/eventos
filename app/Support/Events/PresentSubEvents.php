<?php

declare(strict_types=1);

namespace App\Support\Events;

use App\Domain\Event\Models\Event;
use App\Domain\Form\Models\Registration;
use App\Domain\Form\Models\RegistrationStatus;
use App\Support\Capacity\Models\CapacityHold;
use App\Support\Capacity\Models\CapacityHoldStatus;

/**
 * Événements secondaires d'un événement pour leur écran de gestion :
 * horaires dans le fuseau de la session, places tenues, inscriptions et
 * conflits d'horaires. Traverse Event, Form et la capacité, d'où Support.
 */
final class PresentSubEvents
{
    /**
     * @return list<array{
     *     id: int, title: string, startAt: string, endAt: string, schedule: string,
     *     capacity: ?int, allowWaitlist: bool, people: int, confirmed: int, waitlisted: int,
     *     conflicts: list<string>, checkInUrl: string
     * }>
     */
    public function handle(Event $parent): array
    {
        $subEvents = $parent->subEvents()->orderBy('start_at')->get();
        $ids = $subEvents->pluck('id')->map(fn (mixed $id): int => (int) $id)->all();

        $people = CapacityHold::query()
            ->where('holder_type', 'event')
            ->whereIn('holder_id', array_map(strval(...), $ids))
            ->where('status', CapacityHoldStatus::Held)
            ->groupBy('holder_id')
            ->selectRaw('holder_id, sum(quantity) as total')
            ->pluck('total', 'holder_id');

        $counts = Registration::query()
            ->whereIn('event_id', $ids)
            ->whereIn('status', [RegistrationStatus::Confirmed->value, RegistrationStatus::Waitlisted->value])
            ->groupBy('event_id', 'status')
            ->selectRaw('event_id, status, count(*) as total')
            ->toBase()
            ->get();

        $conflicts = [];

        foreach ($parent->detectSubEventScheduleConflicts() as [$first, $second]) {
            $conflicts[$first->id][] = $second->title;
            $conflicts[$second->id][] = $first->title;
        }

        return $subEvents->map(function (Event $subEvent) use ($people, $counts, $conflicts): array {
            $start = $subEvent->start_at->setTimezone($subEvent->timezone);
            $end = $subEvent->end_at->setTimezone($subEvent->timezone);
            $countFor = fn (RegistrationStatus $status): int => (int) ($counts
                ->first(fn (object $row): bool => (int) $row->event_id === $subEvent->id && $row->status === $status->value)
                ->total ?? 0);

            return [
                'id' => $subEvent->id,
                'title' => $subEvent->title,
                'startAt' => $start->format('Y-m-d\TH:i'),
                'endAt' => $end->format('Y-m-d\TH:i'),
                'schedule' => $start->translatedFormat('l j F Y \à H\hi').' – '.$end->translatedFormat($start->isSameDay($end) ? 'H\hi' : 'l j F \à H\hi'),
                'capacity' => $subEvent->capacity,
                'allowWaitlist' => $subEvent->allow_waitlist,
                'people' => (int) ($people[(string) $subEvent->id] ?? 0),
                'confirmed' => $countFor(RegistrationStatus::Confirmed),
                'waitlisted' => $countFor(RegistrationStatus::Waitlisted),
                'conflicts' => $conflicts[$subEvent->id] ?? [],
                'checkInUrl' => route('events.check-in.index', $subEvent->id),
            ];
        })->values()->all();
    }
}
