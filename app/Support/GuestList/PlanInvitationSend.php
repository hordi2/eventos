<?php

declare(strict_types=1);

namespace App\Support\GuestList;

use App\Domain\Contact\Models\Contact;
use App\Domain\Contact\Models\EventInvitee;
use App\Domain\Event\Models\Event;
use App\Domain\Messaging\Models\MessageChannel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Choisit les destinataires d'un envoi groupé des invitations. Deux façons
 * d'écrire à la liste :
 * - un message par invité ;
 * - un message par groupe (M3.2 : « les communications peuvent être envoyées
 *   au foyer, une seule ») — au premier membre joignable, qui répond pour
 *   tous grâce à son lien personnel.
 *
 * Mêmes règles que tout envoi : pas d'e-mail à un désabonné ou à une adresse
 * invalide, pas de WhatsApp sans l'accord du contact.
 */
final class PlanInvitationSend
{
    public const AUDIENCE_ALL = 'all';

    public const AUDIENCE_UNANSWERED = 'unanswered';

    public const AUDIENCE_SELECTED = 'selected';

    public const MODE_PER_INVITEE = 'per_invitee';

    public const MODE_PER_GROUP = 'per_group';

    /**
     * @param  list<int>  $selectedIds  invités cochés (public « sélection »)
     */
    public function handle(Event $event, MessageChannel $channel, string $audience, string $mode, array $selectedIds = []): InvitationSendPlan
    {
        $invitees = $this->audience($event, $audience, $selectedIds)->get();
        $missing = 0;
        $excluded = 0;
        $covered = 0;
        $recipients = [];

        $batches = $mode === self::MODE_PER_GROUP
            ? $invitees->groupBy(fn (EventInvitee $invitee): string => $invitee->group_key ?? "seul-{$invitee->id}")
            : $invitees->map(fn (EventInvitee $invitee): Collection => collect([$invitee]));

        foreach ($batches as $members) {
            $reachable = $members->first(fn (EventInvitee $invitee): bool => $this->reason($invitee->contact, $channel) === null);

            if ($reachable !== null) {
                $recipients[] = $reachable->id;
                $covered += $members->count() - 1;

                continue;
            }

            foreach ($members as $member) {
                $this->reason($member->contact, $channel) === 'missing' ? $missing++ : $excluded++;
            }
        }

        return new InvitationSendPlan($recipients, $missing, $excluded, $covered);
    }

    /**
     * @param  list<int>  $selectedIds
     * @return Builder<EventInvitee>
     */
    private function audience(Event $event, string $audience, array $selectedIds): Builder
    {
        $query = EventInvitee::query()
            ->with('contact')
            ->whereHas('contact')
            ->where('event_id', $event->id)
            ->orderBy('id');

        return match ($audience) {
            self::AUDIENCE_SELECTED => $query->whereIn('id', $selectedIds),
            self::AUDIENCE_UNANSWERED => $query
                ->whereNotExists(fn ($own) => $own->select(DB::raw(1))->from('registrations')
                    ->whereColumn('registrations.contact_id', 'event_invitees.contact_id')
                    ->where('registrations.event_id', $event->id))
                ->whereNotExists(fn ($accompanying) => $accompanying->select(DB::raw(1))->from('attendees')
                    ->join('registrations', 'registrations.id', '=', 'attendees.registration_id')
                    ->whereColumn('attendees.contact_id', 'event_invitees.contact_id')
                    ->where('registrations.event_id', $event->id)),
            default => $query,
        };
    }

    /**
     * Nul si le contact peut recevoir ce canal ; sinon pourquoi il ne le peut
     * pas : coordonnée absente, ou exclu des envois.
     */
    private function reason(Contact $contact, MessageChannel $channel): ?string
    {
        return match ($channel) {
            MessageChannel::Email => match (true) {
                $contact->email === null => 'missing',
                $contact->isEmailSuppressed() => 'excluded',
                default => null,
            },
            MessageChannel::Whatsapp => match (true) {
                $contact->phone_e164 === null => 'missing',
                $contact->isWhatsappSuppressed() => 'excluded',
                default => null,
            },
        };
    }
}
