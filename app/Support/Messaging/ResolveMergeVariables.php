<?php

declare(strict_types=1);

namespace App\Support\Messaging;

use App\Domain\Contact\Models\Contact;
use App\Domain\Contact\Models\EventInvitee;
use App\Domain\Event\Models\Event;
use App\Domain\Form\Models\Registration;
use App\Domain\Form\Models\RegistrationStatus;
use App\Support\CheckIn\GetContactTableName;
use App\Support\Registration\PersonalAgendaLink;

/**
 * Traverse Contact (Domain/Contact), Event (Domain/Event) et Form
 * (l'inscription, pour « mon agenda ») : ne peut pas vivre dans
 * Domain/Messaging (section 3 du CLAUDE.md), même raisonnement que
 * SendEmailToContact.
 *
 * QR ne figure volontairement pas dans la liste (accord explicite) :
 * T-055 n'a construit de QR que pour les billets payés, pas pour les
 * invités RSVP — rien à résoudre ici tant que ça n'existe pas. `table` a
 * rejoint la liste avec T-065 (plan de table), qui en avait explicitement
 * laissé la place : un contact sans affectation de table garde le repli
 * vide plutôt qu'une valeur inventée.
 */
final class ResolveMergeVariables
{
    /**
     * @var array<string, string>
     */
    private const FALLBACKS = [
        'first_name' => 'cher invité',
        'last_name' => '',
        'full_name' => 'cher invité',
        'rsvp_link' => '#',
        'event_date' => 'date à confirmer',
        'event_location' => 'lieu à confirmer',
        'table' => '',
        'mon_agenda' => '',
    ];

    public function __construct(
        private readonly GetContactTableName $getContactTableName,
        private readonly PersonalAgendaLink $personalAgendaLink,
    ) {}

    public function resolve(string $text, Contact $contact, ?Event $event): string
    {
        $values = $this->values($contact, $event);

        return preg_replace_callback(
            '/\{\{\s*([a-zA-Z0-9_.]+)\s*\}\}/',
            function (array $matches) use ($values): string {
                $key = $matches[1];
                $value = $values[$key] ?? null;

                if ($value !== null && $value !== '') {
                    return $value;
                }

                // Variable connue mais vide pour ce contact : repli propre à
                // elle. Variable totalement inconnue (faute de frappe,
                // variable pas encore prise en charge) : chaîne vide plutôt
                // que de laisser {{...}} brut visible au destinataire —
                // critère explicite du ticket.
                return self::FALLBACKS[$key] ?? '';
            },
            $text,
        ) ?? $text;
    }

    /**
     * @return array<string, string|null>
     */
    private function values(Contact $contact, ?Event $event): array
    {
        $values = [
            'first_name' => $contact->first_name,
            'last_name' => $contact->last_name,
            'full_name' => $contact->fullName(),
            'rsvp_link' => $event !== null ? $this->rsvpLink($event, $contact) : null,
            'event_date' => $event !== null ? $this->eventDate($event) : null,
            'event_location' => $event !== null ? $this->eventLocation($event) : null,
            'table' => $event !== null
                ? $this->getContactTableName->forContact($contact->organization_id, $event->id, $contact->id)
                : null,
            'mon_agenda' => $event !== null ? $this->personalAgenda($event, $contact) : null,
        ];

        foreach ((array) ($contact->custom_fields ?? []) as $key => $value) {
            if (is_scalar($value)) {
                $values["custom_fields.{$key}"] = (string) $value;
            }
        }

        return $values;
    }

    /**
     * Invité de la liste de l'événement : son lien personnel, qui ouvre
     * directement son invitation (et seul ouvre un événement réservé à sa
     * liste). Tout autre contact reçoit le lien public.
     */
    private function rsvpLink(Event $event, Contact $contact): string
    {
        $event->loadMissing('organization');

        $token = EventInvitee::query()
            ->where('event_id', $event->id)
            ->where('contact_id', $contact->id)
            ->value('invitation_token');

        return $token !== null
            ? route('guest.registration.invitation.open', [$event->organization->slug, $event->slug, $token])
            : route('guest.registration.start', [$event->organization->slug, $event->slug]);
    }

    /**
     * « Mon agenda » (D6) : le programme personnel du participant. Vide
     * tant qu'il n'a pas d'inscription active à cet événement — le repli
     * évite alors un lien mort dans le message.
     */
    private function personalAgenda(Event $event, Contact $contact): ?string
    {
        $registration = Registration::query()
            ->where('event_id', $event->id)
            ->where('contact_id', $contact->id)
            ->whereNull('parent_registration_id')
            ->whereIn('status', [RegistrationStatus::Confirmed->value, RegistrationStatus::Pending->value])
            ->latest('id')
            ->first();

        return $registration === null ? null : $this->personalAgendaLink->url($event, $registration);
    }

    private function eventDate(Event $event): string
    {
        // Toujours dans le fuseau de l'événement, jamais celui du serveur
        // (règle 4.3 du CLAUDE.md).
        return $event->start_at->setTimezone($event->timezone)->format('d/m/Y à H:i');
    }

    private function eventLocation(Event $event): string
    {
        if ($event->is_online) {
            return 'En ligne';
        }

        $event->loadMissing('venue');
        $venue = $event->venue;

        return $venue !== null ? $venue->name : 'lieu à confirmer';
    }
}
