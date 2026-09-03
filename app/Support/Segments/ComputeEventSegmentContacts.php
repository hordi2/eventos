<?php

declare(strict_types=1);

namespace App\Support\Segments;

use App\Domain\Contact\Models\Contact;
use App\Domain\Event\Models\Event;
use App\Domain\Form\Models\RegistrationStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

/**
 * Recalcule un segment à la demande, jamais figé (T-042 : « un segment se
 * recalcule à chaque consultation »). Vit hors de Domain/Contact et
 * Domain/Form : les deux modules ne référencent jamais les modèles l'un de
 * l'autre (section 3 du CLAUDE.md, tests d'architecture), donc l'assemblage
 * qui traverse Contact + Registration + Attendee doit se faire ailleurs —
 * comme le fait déjà le contrôleur pour l'historique de participation d'un
 * contact (voir le docblock de Contact::class).
 */
final class ComputeEventSegmentContacts
{
    /**
     * @return Builder<Contact>
     */
    public function query(Event $event, EventSegment $segment): Builder
    {
        return match ($segment) {
            EventSegment::SansReponse => $this->sansReponse($event),
            EventSegment::Confirmes => $this->registeredWithStatus($event, RegistrationStatus::Confirmed),
            EventSegment::Declines => $this->registeredWithStatus($event, RegistrationStatus::Cancelled),
            EventSegment::Presents => $this->presents($event),
            EventSegment::NoShow => $this->noShow($event),
        };
    }

    /**
     * Pointage de présence par contact (T-042, en attendant le vrai
     * check-in T-060/061) — seuls les segments Confirmés/Présents/No-show
     * ont un pointage à afficher, les autres n'ont pas de statut de
     * présence pertinent.
     *
     * @param  list<int>  $contactIds
     * @return array<int, array{attendee_id: int, checked_in_at: ?string}>
     */
    public function attendanceForContacts(Event $event, array $contactIds): array
    {
        if ($contactIds === []) {
            return [];
        }

        return DB::table('attendees')
            ->join('registrations', 'registrations.id', '=', 'attendees.registration_id')
            ->where('registrations.event_id', $event->id)
            ->where('registrations.status', RegistrationStatus::Confirmed->value)
            ->where('attendees.is_primary', true)
            ->whereNull('attendees.deleted_at')
            ->whereNull('registrations.deleted_at')
            ->whereIn('registrations.contact_id', $contactIds)
            ->get(['registrations.contact_id as contact_id', 'attendees.id as attendee_id', 'attendees.checked_in_at'])
            ->keyBy('contact_id')
            ->map(fn (object $row): array => [
                'attendee_id' => (int) $row->attendee_id,
                'checked_in_at' => $row->checked_in_at,
            ])
            ->all();
    }

    /**
     * @return Builder<Contact>
     */
    private function baseContacts(Event $event): Builder
    {
        return Contact::query()->where('organization_id', $event->organization_id);
    }

    /**
     * @return Builder<Contact>
     */
    private function sansReponse(Event $event): Builder
    {
        return $this->baseContacts($event)->whereNotExists(
            fn (QueryBuilder $query) => $this->registrationsForEvent($query, $event),
        );
    }

    /**
     * @return Builder<Contact>
     */
    private function registeredWithStatus(Event $event, RegistrationStatus $status): Builder
    {
        return $this->baseContacts($event)->whereExists(
            fn (QueryBuilder $query) => $this->registrationsForEvent($query, $event)->where('registrations.status', $status->value),
        );
    }

    /**
     * @return Builder<Contact>
     */
    private function presents(Event $event): Builder
    {
        return $this->registeredWithStatus($event, RegistrationStatus::Confirmed)
            ->whereExists(fn (QueryBuilder $query) => $this->checkedInAttendeesForEvent($query, $event));
    }

    /**
     * @return Builder<Contact>
     */
    private function noShow(Event $event): Builder
    {
        if (! $event->end_at->isPast()) {
            // Avant la fin de l'événement, "no-show" n'a pas de sens : on ne
            // peut pas encore savoir qui ne viendra pas.
            return $this->baseContacts($event)->whereRaw('1 = 0');
        }

        return $this->registeredWithStatus($event, RegistrationStatus::Confirmed)
            ->whereNotExists(fn (QueryBuilder $query) => $this->checkedInAttendeesForEvent($query, $event));
    }

    private function registrationsForEvent(QueryBuilder $query, Event $event): QueryBuilder
    {
        return $query->select(DB::raw(1))
            ->from('registrations')
            ->whereColumn('registrations.contact_id', 'contacts.id')
            ->where('registrations.event_id', $event->id)
            ->whereNull('registrations.deleted_at');
    }

    private function checkedInAttendeesForEvent(QueryBuilder $query, Event $event): QueryBuilder
    {
        return $query->select(DB::raw(1))
            ->from('registrations')
            ->join('attendees', 'attendees.registration_id', '=', 'registrations.id')
            ->whereColumn('registrations.contact_id', 'contacts.id')
            ->where('registrations.event_id', $event->id)
            ->where('attendees.is_primary', true)
            ->whereNotNull('attendees.checked_in_at')
            ->whereNull('attendees.deleted_at')
            ->whereNull('registrations.deleted_at');
    }
}
