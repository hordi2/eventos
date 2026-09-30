<?php

declare(strict_types=1);

namespace App\Support\Events;

use App\Domain\Event\Models\Event;
use App\Domain\Event\Models\Speaker;
use Illuminate\Support\Facades\Storage;

/**
 * Intervenants d'un événement, avec les sessions où ils parlent : pour
 * l'écran de l'organisateur comme pour la page publique (D6).
 */
final class PresentEventSpeakers
{
    /**
     * @return list<array{id: int, name: string, role: ?string, company: ?string, bio: ?string, photoUrl: ?string, websiteUrl: ?string, linkedinUrl: ?string, sessionIds: list<int>, sessions: list<string>}>
     */
    public function handle(Event $event): array
    {
        return Speaker::query()
            ->where('event_id', $event->id)
            ->with('sessions')
            ->orderBy('position')
            ->orderBy('id')
            ->get()
            ->map(fn (Speaker $speaker): array => [
                'id' => $speaker->id,
                'name' => $speaker->name,
                'role' => $speaker->role,
                'company' => $speaker->company,
                'bio' => $speaker->bio,
                'photoUrl' => $speaker->photo_path !== null ? Storage::disk('public')->url($speaker->photo_path) : null,
                'websiteUrl' => $speaker->website_url,
                'linkedinUrl' => $speaker->linkedin_url,
                'sessionIds' => $speaker->sessions->pluck('id')->map(fn (mixed $id): int => (int) $id)->values()->all(),
                'sessions' => $speaker->sessions->pluck('title')->values()->all(),
            ])
            ->values()
            ->all();
    }
}
