<?php

declare(strict_types=1);

namespace App\Support\GuestList;

use App\Domain\Contact\Models\Contact;
use App\Domain\Contact\Models\EventInvitee;

/**
 * L'invitation derrière un brouillon de réponse : qui répond, combien
 * d'accompagnants il peut amener, et les autres membres de son groupe, qu'il
 * peut inscrire avec lui (M3.2 « un membre du foyer répond pour tous »).
 */
final class GuestInvitation
{
    /**
     * @param  list<array{inviteeId: int, contactId: int, firstName: string, lastName: string|null, name: string, answered: bool}>  $groupMembers
     */
    public function __construct(
        public readonly EventInvitee $invitee,
        public readonly Contact $contact,
        public readonly int $maxCompanions,
        public readonly array $groupMembers,
    ) {}

    /**
     * Membres du groupe qui n'ont pas encore répondu : les seuls qu'on peut
     * inscrire avec soi.
     *
     * @return list<array{inviteeId: int, contactId: int, firstName: string, lastName: string|null, name: string, answered: bool}>
     */
    public function selectableMembers(): array
    {
        return array_values(array_filter($this->groupMembers, fn (array $member): bool => ! $member['answered']));
    }

    /**
     * Accompagnants du brouillon : d'abord les membres du groupe cochés,
     * reliés à leur contact, puis les accompagnants libres (dans la limite
     * de l'invitation, vérifiée par SaveIdentityRequest). Les membres du
     * groupe ne comptent pas dans cette limite.
     *
     * @param  list<int>  $selectedInviteeIds
     * @param  list<array<string, mixed>>  $extras
     * @return list<array<string, mixed>>
     */
    public function companionsWith(array $selectedInviteeIds, array $extras): array
    {
        $members = array_filter($this->selectableMembers(), fn (array $member): bool => in_array($member['inviteeId'], $selectedInviteeIds, true));

        return [
            ...array_map(fn (array $member): array => [
                'first_name' => $member['firstName'],
                'last_name' => $member['lastName'],
                'contact_id' => $member['contactId'],
                'invitee_id' => $member['inviteeId'],
            ], array_values($members)),
            ...$extras,
        ];
    }
}
