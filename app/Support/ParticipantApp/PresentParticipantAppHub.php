<?php

declare(strict_types=1);

namespace App\Support\ParticipantApp;

use App\Domain\Event\Models\Event;
use App\Domain\Messaging\Models\PushNotification;
use App\Support\Events\PresentEventSessions;

/**
 * La page d'accueil de l'application participant (D9) : programme et plan du
 * lieu, le fil des notifications déjà diffusées, plus un repère vers chaque
 * autre pièce déjà construite — l'annuaire (D8), que cette page n'a pas à
 * redire.
 *
 * Traverse Event seul pour les données qu'elle expose, mais appelle
 * PresentEventSessions (Support/Events) et lit PushNotification
 * (Domain/Messaging) : sa place est donc également dans Support, hors de
 * tout module Domain/ (section 3 du CLAUDE.md).
 */
final class PresentParticipantAppHub
{
    /**
     * @return array{programme: list<array<string, mixed>>, venue: ?array{name: string, address: ?string, mapUrl: string, accessInstructions: ?string, parkingInfo: ?string}, notifications: list<array{id: int, title: string, body: string, sentAt: string}>}
     */
    public function handle(Event $event): array
    {
        return [
            'programme' => app(PresentEventSessions::class)->handle($event),
            'venue' => $this->venue($event),
            'notifications' => $this->notifications($event),
        ];
    }

    /**
     * @return list<array{id: int, title: string, body: string, sentAt: string}>
     */
    private function notifications(Event $event): array
    {
        return PushNotification::query()
            ->where('event_id', $event->id)
            ->whereNotNull('sent_at')
            ->latest('sent_at')
            ->limit(20)
            ->get(['id', 'title', 'body', 'sent_at'])
            ->map(fn (PushNotification $notification): array => [
                'id' => $notification->id,
                'title' => $notification->title,
                'body' => $notification->body,
                'sentAt' => $notification->sent_at->toIso8601String(),
            ])
            ->all();
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
