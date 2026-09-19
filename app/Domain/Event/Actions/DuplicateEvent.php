<?php

declare(strict_types=1);

namespace App\Domain\Event\Actions;

use App\Domain\Event\Data\EventDuplicationPart;
use App\Domain\Event\Events\EventDuplicated;
use App\Domain\Event\Models\Event;
use App\Models\User;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;

/**
 * Duplique un événement (et ses sous-événements) avec toutes ses dates
 * décalées du même delta — jamais copiées telles quelles (M1.1.3 du CDC).
 * Le lieu est réutilisé, pas copié. Formulaires, page, billets, liste
 * d'invités et messages, au choix de l'organisateur, sont recopiés par
 * leurs modules en réponse à EventDuplicated.
 */
final class DuplicateEvent
{
    public function __construct(
        private readonly CreateEvent $createEvent,
    ) {}

    /**
     * @param  list<EventDuplicationPart>  $parts
     */
    public function handle(Event $source, User $duplicator, DateTimeInterface $newStartAt, array $parts = []): Event
    {
        $source->loadMissing(['organization', 'subEvents']);

        $organization = $source->organization;
        $offsetSeconds = (int) $source->start_at->diffInSeconds(CarbonImmutable::instance($newStartAt), false);

        return DB::transaction(function () use ($source, $duplicator, $organization, $offsetSeconds, $parts): Event {
            $duplicate = $this->copy($source, $duplicator, $offsetSeconds, null);
            $eventIdMap = [$source->id => $duplicate->id];

            foreach ($source->subEvents as $subEvent) {
                $eventIdMap[$subEvent->id] = $this->copy($subEvent, $duplicator, $offsetSeconds, $duplicate->id)->id;
            }

            EventDuplicated::dispatch($organization, $duplicator, $eventIdMap, $offsetSeconds, $parts);

            return $duplicate->refresh();
        });
    }

    private function copy(Event $original, User $duplicator, int $offsetSeconds, ?int $parentEventId): Event
    {
        $copy = $this->createEvent->handle($original->organization, $duplicator, [
            ...$this->shiftedData($original, $offsetSeconds),
            'parent_event_id' => $parentEventId,
        ]);

        // Hors de CreateEvent, qui ne reçoit jamais un mot de passe déjà
        // chiffré ; sans événement de modèle, pour que l'empreinte du mot de
        // passe n'entre jamais dans le journal d'audit, immuable.
        $copy->forceFill([
            'password_hash' => $original->password_hash,
            'registration_closed_message' => $original->registration_closed_message,
        ])->saveQuietly();

        return $copy;
    }

    /**
     * @return array<string, mixed>
     */
    private function shiftedData(Event $original, int $offsetSeconds): array
    {
        return [
            'title' => $original->title,
            'subtitle' => $original->subtitle,
            'description' => $original->description,
            'type' => $original->type,
            'audience' => $original->audience,
            'start_at' => $original->start_at->addSeconds($offsetSeconds),
            'end_at' => $original->end_at->addSeconds($offsetSeconds),
            'timezone' => $original->timezone,
            'is_online' => $original->is_online,
            'online_url' => $original->online_url,
            'venue_id' => $original->venue_id,
            'capacity' => $original->capacity,
            'registration_opens_at' => $original->registration_opens_at?->addSeconds($offsetSeconds),
            'registration_closes_at' => $original->registration_closes_at?->addSeconds($offsetSeconds),
            'access_mode' => $original->access_mode,
            'requires_approval' => $original->requires_approval,
            'allow_waitlist' => $original->allow_waitlist,
            'allow_guest_edit' => $original->allow_guest_edit,
            'edit_deadline' => $original->edit_deadline?->addSeconds($offsetSeconds),
            'currency' => $original->currency,
        ];
    }
}
