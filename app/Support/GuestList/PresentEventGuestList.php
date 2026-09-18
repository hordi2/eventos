<?php

declare(strict_types=1);

namespace App\Support\GuestList;

use App\Domain\Contact\Models\EventInvitee;
use App\Domain\Contact\Models\Tag;
use App\Domain\Contact\Support\CompanionAllowance;
use App\Domain\Event\Models\Event;
use App\Domain\Form\Models\Attendee;
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

        $contactIds = $paginator->getCollection()->pluck('contact_id');
        $responses = Registration::query()
            ->where('event_id', $event->id)
            ->whereIn('contact_id', $contactIds)
            ->whereNull('parent_registration_id')
            ->get(['contact_id', 'status'])
            ->keyBy('contact_id');
        $accompanying = $this->accompanyingResponses($event, $contactIds->all());

        return [
            'invitees' => $paginator->through(fn (EventInvitee $invitee): array => $this->present($event, $invitee, $responses->get($invitee->contact_id), $accompanying)),
            'stats' => $this->stats($event),
            'groups' => $this->baseQuery($event)->whereNotNull('event_invitees.group_key')->orderBy('event_invitees.group_key')->pluck('event_invitees.group_key')->map(fn (mixed $key): string => (string) $key)->unique()->values()->all(),
            'tags' => Tag::query()->orderBy('name')->get(['name', 'color'])->map(fn (Tag $tag): array => ['name' => $tag->name, 'color' => $tag->color])->all(),
        ];
    }

    /**
     * Sans inscription à son nom, la réponse d'un invité peut être celle du
     * membre de son groupe qui l'a inscrit.
     *
     * @param  array<int, array{status: string, label: string}>  $accompanying
     * @return array<string, mixed>
     */
    private function present(Event $event, EventInvitee $invitee, ?Registration $registration, array $accompanying): array
    {
        $row = [...$this->row($invitee, $registration), ...$this->links($event, $invitee)];

        if ($registration === null) {
            $row['response'] = $accompanying[$invitee->contact_id] ?? null;
        }

        return $row;
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
     * Membres d'un groupe venus avec un autre invité : leur réponse est celle
     * de l'inscription qui les porte, « avec » son titulaire.
     *
     * @param  list<int>  $contactIds
     * @return array<int, array{status: string, label: string}>
     */
    private function accompanyingResponses(Event $event, array $contactIds): array
    {
        $responses = [];

        $attendees = Attendee::query()
            ->with('registration:id,status,first_name,last_name,email')
            ->whereIn('contact_id', $contactIds)
            ->where('is_primary', false)
            ->whereHas('registration', fn ($registration) => $registration->where('event_id', $event->id))
            ->get(['contact_id', 'registration_id']);

        foreach ($attendees as $attendee) {
            $registration = $attendee->registration;
            $holder = trim("{$registration->first_name} {$registration->last_name}") ?: $registration->email;
            $responses[(int) $attendee->contact_id] = [
                'status' => $registration->status->value,
                'label' => "{$registration->status->label()} · avec {$holder}",
            ];
        }

        return $responses;
    }

    /**
     * Lien personnel de l'invité, et le même, prêt à partir sur WhatsApp
     * quand son numéro est connu (canal de premier rang, §1 du CLAUDE.md).
     *
     * @return array{personalUrl: string, whatsappUrl: string|null}
     */
    private function links(Event $event, EventInvitee $invitee): array
    {
        $personalUrl = route('guest.registration.invitation.open', [$event->organization->slug, $event->slug, $invitee->invitation_token]);
        $phone = $invitee->contact->phone_e164;
        $greeting = $invitee->contact->first_name !== null ? "Bonjour {$invitee->contact->first_name}, v" : 'V';
        $message = "{$greeting}oici votre invitation personnelle pour « {$event->title} » : {$personalUrl}";

        return [
            'personalUrl' => $personalUrl,
            'whatsappUrl' => $phone === null ? null : 'https://wa.me/'.ltrim($phone, '+').'?text='.rawurlencode($message),
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
                ->where(fn ($query) => $query
                    ->whereExists(fn ($own) => $own->select(DB::raw(1))
                        ->from('registrations')
                        ->whereColumn('registrations.contact_id', 'event_invitees.contact_id')
                        ->where('registrations.event_id', $event->id)
                        ->whereNull('registrations.parent_registration_id'))
                    ->orWhereExists(fn ($accompanying) => $accompanying->select(DB::raw(1))
                        ->from('attendees')
                        ->join('registrations', 'registrations.id', '=', 'attendees.registration_id')
                        ->whereColumn('attendees.contact_id', 'event_invitees.contact_id')
                        ->where('registrations.event_id', $event->id)))
                ->count(),
        ];
    }
}
