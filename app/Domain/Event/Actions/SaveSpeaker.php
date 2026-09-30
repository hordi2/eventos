<?php

declare(strict_types=1);

namespace App\Domain\Event\Actions;

use App\Domain\Event\Models\Event;
use App\Domain\Event\Models\Speaker;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Fiche d'un intervenant (D6) et les sessions où il parle. Seules les
 * sessions de cet événement sont acceptées : une session d'un autre
 * événement n'a rien à faire dans son programme.
 */
final class SaveSpeaker
{
    /**
     * @param  array<string, mixed>  $data
     * @param  list<int>  $sessionIds
     */
    public function create(Event $event, User $editor, array $data, array $sessionIds = []): Speaker
    {
        Gate::forUser($editor)->authorize('update', $event);

        return DB::transaction(function () use ($event, $data, $sessionIds): Speaker {
            $speaker = Speaker::query()->create([
                'organization_id' => $event->organization_id,
                'event_id' => $event->id,
                ...$this->attributes($data),
                'position' => Speaker::query()->where('event_id', $event->id)->count(),
            ]);

            $this->syncSessions($event, $speaker, $sessionIds);

            return $speaker;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<int>  $sessionIds
     */
    public function update(Speaker $speaker, Event $event, User $editor, array $data, array $sessionIds = []): Speaker
    {
        Gate::forUser($editor)->authorize('update', $event);

        DB::transaction(function () use ($speaker, $event, $data, $sessionIds): void {
            $speaker->update($this->attributes($data));
            $this->syncSessions($event, $speaker, $sessionIds);
        });

        return $speaker->refresh();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(array $data): array
    {
        return [
            'name' => $data['name'],
            'role' => $data['role'] ?? null,
            'company' => $data['company'] ?? null,
            'email' => $data['email'] ?? null,
            'bio' => $data['bio'] ?? null,
            'website_url' => $data['website_url'] ?? null,
            'linkedin_url' => $data['linkedin_url'] ?? null,
        ];
    }

    /**
     * @param  list<int>  $sessionIds
     */
    private function syncSessions(Event $event, Speaker $speaker, array $sessionIds): void
    {
        $ownSessions = Event::query()
            ->where('parent_event_id', $event->id)
            ->whereIn('id', $sessionIds)
            ->pluck('id')
            ->all();

        // sync() ne sait pas porter les colonnes du pivot : l'organisation
        // (RLS) est posée session par session.
        $speaker->sessions()->sync(array_fill_keys($ownSessions, ['organization_id' => $event->organization_id]));
    }
}
