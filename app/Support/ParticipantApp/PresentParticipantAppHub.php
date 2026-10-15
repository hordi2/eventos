<?php

declare(strict_types=1);

namespace App\Support\ParticipantApp;

use App\Domain\Event\Models\Event;
use App\Support\Events\PresentEventSessions;

/**
 * La page d'accueil de l'application participant (D9) : programme et plan du
 * lieu, plus un repère vers chaque autre pièce déjà construite — l'annuaire
 * (D8), que cette page n'a pas à redire.
 *
 * Traverse Event seul seul pour les données qu'elle expose, mais appelle
 * PresentEventSessions (Support/Events) : sa place est donc également dans
 * Support (section 3 du CLAUDE.md).
 */
final class PresentParticipantAppHub
{
    /**
     * @return array{programme: list<array<string, mixed>>, venue: ?array{name: string, address: ?string, mapUrl: string, accessInstructions: ?string, parkingInfo: ?string}}
     */
    public function handle(Event $event): array
    {
        return [
            'programme' => app(PresentEventSessions::class)->handle($event),
            'venue' => $this->venue($event),
        ];
    }

    /**
     * @return ?array{name: string, address: ?string, mapUrl: string, accessInstructions: ?string, parkingInfo: ?string}
     */
    private function venue(Event $event): ?array
    {
        $venue = $event->is_online ? null : $event->venue;

        if ($venue === null) {
            return null;
        }

        $query = $venue->latitude !== null && $venue->longitude !== null
            ? "{$venue->latitude},{$venue->longitude}"
            : (string) ($venue->address ?? $venue->name);

        return [
            'name' => $venue->name,
            'address' => $venue->address,
            'mapUrl' => 'https://www.google.com/maps/search/?api=1&query='.rawurlencode($query),
            'accessInstructions' => $venue->access_instructions,
            'parkingInfo' => $venue->parking_info,
        ];
    }
}
