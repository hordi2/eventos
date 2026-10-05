<?php

declare(strict_types=1);

namespace App\Support\Networking;

use App\Domain\Event\Models\Event;
use App\Domain\Form\Models\AttendeeMeeting;
use App\Domain\Form\Models\AttendeeMeetingStatus;
use App\Domain\Form\Models\Registration;
use App\Domain\Form\Models\RegistrationStatus;
use Carbon\CarbonImmutable;

/**
 * Rendez-vous entre participants, pendant l'événement (D8).
 *
 * On ne propose un rendez-vous qu'à quelqu'un qui figure à l'annuaire, et
 * seulement sur le créneau de l'événement : un rendez-vous la veille ou trois
 * jours après n'aurait pas de sens pour une rencontre sur place.
 *
 * Traverse Event et Form : sa place est dans Support (section 3 du CLAUDE.md).
 */
final class PlanAttendeeMeeting
{
    /**
     * Propose un rendez-vous. Null quand l'autre ne peut pas être invité :
     * absent de l'annuaire, pas confirmé, ou c'est soi-même.
     */
    public function propose(
        Event $event,
        Registration $requester,
        int $guestRegistrationId,
        CarbonImmutable $startsAt,
        int $durationMinutes,
        ?string $place,
        ?string $message,
    ): ?AttendeeMeeting {
        $guest = $this->attendee($event, $guestRegistrationId);

        if ($guest === null || $guest->id === $requester->id || $requester->directory_consent_at === null) {
            return null;
        }

        if (! $this->isDuringEvent($event, $startsAt)) {
            return null;
        }

        return AttendeeMeeting::query()->create([
            'organization_id' => $event->organization_id,
            'event_id' => $event->id,
            'requester_registration_id' => $requester->id,
            'guest_registration_id' => $guest->id,
            'starts_at' => $startsAt,
            'duration_minutes' => max(5, min(240, $durationMinutes)),
            'place' => $place,
            'message' => $message,
            'status' => AttendeeMeetingStatus::Pending,
        ]);
    }

    /**
     * L'invité accepte ou décline ; celui qui a proposé peut annuler. Un
     * rendez-vous déjà tranché ne change plus d'avis (règle 4.4).
     */
    public function answer(AttendeeMeeting $meeting, Registration $participant, AttendeeMeetingStatus $status): bool
    {
        if ($meeting->status !== AttendeeMeetingStatus::Pending) {
            return false;
        }

        $isGuest = $meeting->guest_registration_id === $participant->id;
        $isRequester = $meeting->requester_registration_id === $participant->id;

        $allowed = match ($status) {
            AttendeeMeetingStatus::Accepted, AttendeeMeetingStatus::Declined => $isGuest,
            AttendeeMeetingStatus::Cancelled => $isRequester,
            default => false,
        };

        if (! $allowed) {
            return false;
        }

        $meeting->update(['status' => $status, 'answered_at' => CarbonImmutable::now()]);

        return true;
    }

    /**
     * Les rendez-vous d'un participant, proposés comme reçus, du plus proche
     * au plus lointain. Les heures sont rendues dans le fuseau de
     * l'événement (règle 4.3).
     *
     * @return list<array{id: int, name: string, when: string, place: ?string, message: ?string, status: string, statusLabel: string, mine: bool, pending: bool}>
     */
    public function forAttendee(Event $event, Registration $registration): array
    {
        $meetings = AttendeeMeeting::query()
            ->where('event_id', $event->id)
            ->where(fn ($query) => $query
                ->where('requester_registration_id', $registration->id)
                ->orWhere('guest_registration_id', $registration->id))
            ->with(['requester', 'guest'])
            ->orderBy('starts_at')
            ->get();

        $rows = [];

        foreach ($meetings as $meeting) {
            $mine = $meeting->requester_registration_id === $registration->id;
            $other = $mine ? $meeting->guest : $meeting->requester;

            if ($other === null) {
                continue;
            }

            $rows[] = [
                'id' => $meeting->id,
                'name' => trim("{$other->first_name} {$other->last_name}"),
                'when' => $meeting->starts_at->setTimezone($event->timezone)->translatedFormat('l j F, H\\hi'),
                'place' => $meeting->place,
                'message' => $meeting->message,
                'status' => $meeting->status->value,
                'statusLabel' => $meeting->status->label(),
                'mine' => $mine,
                'pending' => $meeting->status === AttendeeMeetingStatus::Pending,
            ];
        }

        return $rows;
    }

    private function attendee(Event $event, int $registrationId): ?Registration
    {
        return Registration::query()
            ->where('event_id', $event->id)
            ->where('status', RegistrationStatus::Confirmed)
            ->whereNotNull('directory_consent_at')
            ->find($registrationId);
    }

    private function isDuringEvent(Event $event, CarbonImmutable $startsAt): bool
    {
        $start = $event->start_at->subHours(2);
        $end = ($event->end_at ?? $event->start_at->addDay())->addHours(2);

        return $startsAt->betweenIncluded($start, $end);
    }
}
