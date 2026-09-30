<?php

declare(strict_types=1);

namespace App\Support\Registration;

use App\Domain\Event\Models\Event;
use App\Domain\Event\Models\Speaker;
use App\Domain\Form\Models\Registration;
use App\Domain\Form\Models\RegistrationStatus;

/**
 * Programme personnel d'un participant (D6, « mon agenda ») : les sessions
 * qu'il a choisies au moment de son inscription, dans l'ordre où il les
 * vivra. Traverse Form (l'inscription) et Event (les sessions), d'où sa
 * place dans Support (section 3 du CLAUDE.md).
 *
 * Les sessions choisies sont des inscriptions rattachées
 * (parent_registration_id, T-013) : rien de neuf n'est stocké ici, et une
 * place en liste d'attente se lit telle quelle.
 */
final class PresentPersonalAgenda
{
    /**
     * Sessions retenues par ce participant, les plus proches d'abord.
     *
     * @return list<array{id: int, title: string, day: string, time: string, room: ?string, speakers: list<string>, isWaitlisted: bool}>
     */
    public function handle(Registration $registration, Event $event): array
    {
        $statuses = $this->statuses($registration);

        return array_map(function (Event $session) use ($event, $statuses): array {
            $start = $session->start_at->setTimezone($event->timezone);
            $end = $session->end_at->setTimezone($event->timezone);

            return [
                'id' => $session->id,
                'title' => $session->title,
                'day' => $start->translatedFormat('l j F'),
                'time' => $start->format('H\hi').' – '.$end->format('H\hi'),
                'room' => $session->room,
                'speakers' => $session->speakers->map(fn (Speaker $speaker): string => $speaker->name)->values()->all(),
                'isWaitlisted' => ($statuses[$session->id] ?? null) === RegistrationStatus::Waitlisted,
            ];
        }, $this->sessions($registration, $event));
    }

    /**
     * Les mêmes sessions, en modèles : pour le fichier d'agenda.
     *
     * @return list<Event>
     */
    public function sessions(Registration $registration, Event $event): array
    {
        $ids = array_keys($this->statuses($registration));

        if ($ids === []) {
            return [];
        }

        return Event::query()
            ->whereIn('id', $ids)
            ->where('parent_event_id', $event->id)
            ->with('speakers')
            ->orderBy('start_at')
            ->get()
            ->all();
    }

    /**
     * Statut de ce participant pour chaque session retenue. Une session
     * annulée ou refusée n'a plus sa place dans son programme.
     *
     * @return array<int, RegistrationStatus>
     */
    private function statuses(Registration $registration): array
    {
        return $registration->subEventRegistrations()
            ->whereIn('status', [
                RegistrationStatus::Confirmed->value,
                RegistrationStatus::Pending->value,
                RegistrationStatus::Waitlisted->value,
            ])
            ->get()
            ->mapWithKeys(fn (Registration $child): array => [(int) $child->event_id => $child->status])
            ->all();
    }
}
