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
     * Sessions qui se chevauchent dans une même salle.
     *
     * @param  list<Event>  $sessions
     * @return list<array{0: Event, 1: Event}>
     */
    private function roomClashes(array $sessions): array
    {
        $clashes = [];

        foreach ($sessions as $index => $session) {
            foreach (array_slice($sessions, $index + 1) as $other) {
                $sameRoom = $session->room !== null && $other->room !== null
                    && mb_strtolower(trim($session->room)) === mb_strtolower(trim($other->room));

                if ($sameRoom && $session->start_at->lessThan($other->end_at) && $other->start_at->lessThan($session->end_at)) {
                    $clashes[] = [$session, $other];
                }
            }
        }

        return $clashes;
    }

    /**
     * @return list<array{
     *     id: int, title: string, startAt: string, endAt: string, schedule: string,
     *     capacity: ?int, allowWaitlist: bool, people: int, confirmed: int, waitlisted: int,
     *     conflicts: list<string>, checkInUrl: string
     * }>
     */
    public function handle(Event $parent): array
    {
        $subEvents = $parent->subEvents()->with('speakers')->orderBy('start_at')->get();
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
            ->whereIn('status', [RegistrationStatus::Confirmed->value, RegistrationStatus::Pending->value, RegistrationStatus::Waitlisted->value])
            ->groupBy('event_id', 'status')
            ->selectRaw('event_id, status, count(*) as total')
            ->toBase()
            ->get();

        $conflicts = [];

        foreach ($parent->detectSubEventScheduleConflicts() as [$first, $second]) {
            $conflicts[$first->id][] = $second->title;
            $conflicts[$second->id][] = $first->title;
        }

        // Deux sessions dans la même salle au même moment : le public ne
        // peut pas être aux deux, et la salle encore moins (D6).
        foreach ($this->roomClashes($subEvents->all()) as [$first, $second]) {
            $conflicts[$first->id][] = "{$second->title} (même salle)";
            $conflicts[$second->id][] = "{$first->title} (même salle)";
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
                'room' => $subEvent->room,
                'speakers' => $subEvent->speakers->pluck('name')->values()->all(),
                'allowWaitlist' => $subEvent->allow_waitlist,
                'people' => (int) ($people[(string) $subEvent->id] ?? 0),
                // Une demande en attente tient déjà sa place dans la session.
                'confirmed' => $countFor(RegistrationStatus::Confirmed) + $countFor(RegistrationStatus::Pending),
                'waitlisted' => $countFor(RegistrationStatus::Waitlisted),
                'conflicts' => $conflicts[$subEvent->id] ?? [],
                'checkInUrl' => route('events.check-in.index', $subEvent->id),
            ];
        })->values()->all();
    }
}
