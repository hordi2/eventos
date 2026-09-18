<?php

declare(strict_types=1);

namespace App\Support\GuestList;

use App\Domain\Contact\Models\EventInvitee;
use App\Domain\Form\Models\Attendee;
use App\Domain\Form\Models\Registration;
use App\Domain\Form\Models\RegistrationDraft;
use App\Domain\Form\Support\FormSettings;

/**
 * Hors des modules (App\Support) : relie un brouillon (Domain/Form) à son
 * invitation (Domain/Contact).
 */
final class ResolveGuestInvitation
{
    public function handle(RegistrationDraft $draft): ?GuestInvitation
    {
        if ($draft->event_invitee_id === null) {
            return null;
        }

        $invitee = EventInvitee::query()->with('contact')->whereHas('contact')->find($draft->event_invitee_id);

        if ($invitee === null) {
            return null;
        }

        return new GuestInvitation(
            invitee: $invitee,
            contact: $invitee->contact,
            // « Illimité » reste borné par la limite de la plateforme.
            maxCompanions: $invitee->companions_allowed ?? FormSettings::MAX_COMPANIONS,
            groupMembers: $this->groupMembers($invitee),
        );
    }

    /**
     * @return list<array{inviteeId: int, contactId: int, firstName: string, lastName: string|null, name: string, answered: bool}>
     */
    private function groupMembers(EventInvitee $invitee): array
    {
        if ($invitee->group_key === null) {
            return [];
        }

        $members = EventInvitee::query()
            ->with('contact')
            ->whereHas('contact')
            ->where('event_id', $invitee->event_id)
            ->where('group_key', $invitee->group_key)
            ->whereKeyNot($invitee->id)
            ->orderBy('id')
            ->get();

        $answered = $this->answeredContactIds($invitee->event_id, $members->pluck('contact_id')->all());

        return $members->map(fn (EventInvitee $member): array => [
            'inviteeId' => $member->id,
            'contactId' => $member->contact_id,
            'firstName' => (string) ($member->contact->first_name ?? $member->contact->fullName()),
            'lastName' => $member->contact->last_name,
            'name' => $member->contact->fullName(),
            'answered' => in_array($member->contact_id, $answered, true),
        ])->values()->all();
    }

    /**
     * Ont déjà répondu : ceux qui ont leur propre inscription, ou qui viennent
     * avec quelqu'un d'autre.
     *
     * @param  list<int>  $contactIds
     * @return list<int>
     */
    private function answeredContactIds(int $eventId, array $contactIds): array
    {
        $own = Registration::query()->where('event_id', $eventId)->whereIn('contact_id', $contactIds)->pluck('contact_id');
        $accompanying = Attendee::query()
            ->whereIn('contact_id', $contactIds)
            ->whereHas('registration', fn ($registration) => $registration->where('event_id', $eventId))
            ->pluck('contact_id');

        return $own->merge($accompanying)->map(fn (mixed $id): int => (int) $id)->unique()->values()->all();
    }
}
