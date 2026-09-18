<?php

declare(strict_types=1);

namespace App\Support\GuestList;

use App\Domain\Contact\Models\EventInvitee;
use App\Domain\Contact\Models\Tag;
use App\Domain\Contact\Support\CompanionAllowance;
use App\Domain\Event\Models\Event;
use App\Domain\Form\Models\Registration;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Écran « Liste des invités » d'un événement. Hors des modules (App\Support)
 * parce qu'il croise la liste (Domain/Contact) et les réponses reçues
 * (Domain/Form) : la réponse de chaque invité n'est pas stockée sur la liste,
 * elle se lit dans ses inscriptions — jamais deux vérités.
 *
 * Les membres d'un même groupe se suivent ; les invités seuls viennent
 * ensuite, par nom.
 */
final class PresentEventGuestList
{
    public const PER_PAGE = 50;

    /**
     * @return array{invitees: LengthAwarePaginator<int, array<string, mixed>>, stats: array{invitees: int, groups: int, responded: int}, groups: array<int, string>, tags: array<int, array{name: string, color: string}>}
     */
    public function handle(Event $event, string $search): array
    {
        $paginator = $this->baseQuery($event)
            ->with('contact.tags:id,name,color')
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $inner) use ($search): void {
                    $inner->where('contacts.first_name', 'ilike', "%{$search}%")
                        ->orWhere('contacts.last_name', 'ilike', "%{$search}%")
                        ->orWhere('contacts.email', 'ilike', "%{$search}%")
                        ->orWhere('event_invitees.group_key', 'ilike', "%{$search}%");
                });
            })
            ->orderByRaw('event_invitees.group_key is null, event_invitees.group_key')
            ->orderBy('contacts.last_name')
            ->orderBy('contacts.first_name')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        $responses = Registration::query()
            ->where('event_id', $event->id)
            ->whereIn('contact_id', $paginator->getCollection()->pluck('contact_id'))
            ->whereNull('parent_registration_id')
            ->get(['contact_id', 'status'])
            ->keyBy('contact_id');

        return [
            'invitees' => $paginator->through(fn (EventInvitee $invitee): array => $this->row($invitee, $responses->get($invitee->contact_id))),
            'stats' => $this->stats($event),
            'groups' => $this->baseQuery($event)->whereNotNull('event_invitees.group_key')->orderBy('event_invitees.group_key')->pluck('event_invitees.group_key')->map(fn (mixed $key): string => (string) $key)->unique()->values()->all(),
            'tags' => Tag::query()->orderBy('name')->get(['name', 'color'])->map(fn (Tag $tag): array => ['name' => $tag->name, 'color' => $tag->color])->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function row(EventInvitee $invitee, ?Registration $registration): array
    {
        return [
            'id' => $invitee->id,
            'firstName' => $invitee->contact->first_name,
            'lastName' => $invitee->contact->last_name,
            'fullName' => $invitee->contact->fullName(),
            'email' => $invitee->contact->email,
            'phone' => $invitee->contact->phone_e164,
            'groupKey' => $invitee->group_key,
            'companionsAllowed' => $invitee->companions_allowed,
            'companionsLabel' => CompanionAllowance::label($invitee->companions_allowed),
            'ccEmail' => $invitee->cc_email,
            'tags' => $invitee->contact->tags->map(fn (Tag $tag): array => ['name' => $tag->name, 'color' => $tag->color])->values()->all(),
            'response' => $registration === null ? null : ['status' => $registration->status->value, 'label' => $registration->status->label()],
        ];
    }

    /**
     * @return Builder<EventInvitee>
     */
    private function baseQuery(Event $event): Builder
    {
        return EventInvitee::query()
            ->select('event_invitees.*')
            ->join('contacts', 'contacts.id', '=', 'event_invitees.contact_id')
            ->whereNull('contacts.deleted_at')
            ->where('event_invitees.event_id', $event->id);
    }

    /**
     * @return array{invitees: int, groups: int, responded: int}
     */
    private function stats(Event $event): array
    {
        return [
            'invitees' => $this->baseQuery($event)->count(),
            'groups' => $this->baseQuery($event)->whereNotNull('event_invitees.group_key')->distinct()->count('event_invitees.group_key'),
            'responded' => $this->baseQuery($event)
                ->whereExists(fn ($query) => $query->select(DB::raw(1))
                    ->from('registrations')
                    ->whereColumn('registrations.contact_id', 'event_invitees.contact_id')
                    ->where('registrations.event_id', $event->id)
                    ->whereNull('registrations.parent_registration_id'))
                ->count(),
        ];
    }
}
