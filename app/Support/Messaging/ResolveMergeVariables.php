<?php

declare(strict_types=1);

namespace App\Support\Messaging;

use App\Domain\Contact\Models\Contact;
use App\Domain\Event\Models\Event;

/**
 * Traverse Contact (Domain/Contact) et Event (Domain/Event) : ne peut pas
 * vivre dans Domain/Messaging (section 3 du CLAUDE.md), même raisonnement
 * que SendEmailToContact.
 *
 * QR et table ne figurent volontairement pas dans la liste (accord
 * explicite) : aucune des deux fonctionnalités n'existe encore dans l'app
 * (T-055 pour les QR, pas de plan de table). Ajouter une entrée ici et un
 * repli dans self::FALLBACKS suffira le jour où elles existeront — aucune
 * réécriture du moteur.
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
    ];

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
            'rsvp_link' => $event !== null ? $this->rsvpLink($event) : null,
            'event_date' => $event !== null ? $this->eventDate($event) : null,
            'event_location' => $event !== null ? $this->eventLocation($event) : null,
        ];

        foreach ((array) ($contact->custom_fields ?? []) as $key => $value) {
            if (is_scalar($value)) {
                $values["custom_fields.{$key}"] = (string) $value;
            }
        }

        return $values;
    }

    private function rsvpLink(Event $event): string
    {
        $event->loadMissing('organization');

        return route('guest.registration.start', [$event->organization->slug, $event->slug]);
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
