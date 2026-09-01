<?php

declare(strict_types=1);

namespace App\Domain\Event\Actions;

use App\Domain\Event\Models\Event;
use App\Models\User;
use Carbon\CarbonImmutable;
use DateTimeInterface;

/**
 * Duplique un événement (et ses sous-événements) avec toutes ses dates
 * décalées du même delta — jamais copiées telles quelles (M1.1.3 du CDC).
 *
 * Le cahier des charges prévoit aussi de choisir quoi emporter parmi le
 * formulaire, la page, les tarifs, la liste d'invités et les séquences
 * d'e-mails : aucun de ces modules n'existe encore dans le code (Sprints
 * 3, 5 et 6), donc rien à dupliquer de ce côté pour l'instant. Ce ticket
 * couvre uniquement ce qui existe aujourd'hui : l'événement, son lieu
 * (réutilisé, pas copié) et ses sous-événements.
 */
final class DuplicateEvent
{
    public function __construct(
        private readonly CreateEvent $createEvent,
    ) {}

    public function handle(Event $source, User $duplicator, DateTimeInterface $newStartAt): Event
    {
        $source->loadMissing(['organization', 'subEvents']);

        $organization = $source->organization;
        $offsetSeconds = (int) $source->start_at->diffInSeconds(CarbonImmutable::instance($newStartAt), false);

        $duplicate = $this->createEvent->handle($organization, $duplicator, $this->shiftedData($source, $offsetSeconds));

        foreach ($source->subEvents as $subEvent) {
            $this->createEvent->handle($organization, $duplicator, [
                ...$this->shiftedData($subEvent, $offsetSeconds),
                'parent_event_id' => $duplicate->id,
            ]);
        }

        return $duplicate->refresh();
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
