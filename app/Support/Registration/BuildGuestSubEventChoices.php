<?php

declare(strict_types=1);

namespace App\Support\Registration;

use App\Domain\Event\Models\Event;
use App\Support\Capacity\Actions\GetRemainingCapacity;

/**
 * Sessions d'un événement telles que l'invité les voit dans le bloc
 * « Événements secondaires » : horaires dans le fuseau de la session et
 * places restantes en temps réel.
 */
final class BuildGuestSubEventChoices
{
    public function __construct(
        private readonly GetRemainingCapacity $getRemainingCapacity,
    ) {}

    /**
     * @return array<int, array{title: string, schedule: string, availability: ?string, closed: bool}>
     */
    public function handle(Event $event): array
    {
        $choices = [];

        foreach ($event->subEvents()->orderBy('start_at')->get() as $subEvent) {
            $remaining = $this->getRemainingCapacity->handle('event', (string) $subEvent->id, $subEvent->capacity);
            $start = $subEvent->start_at->setTimezone($subEvent->timezone);
            $end = $subEvent->end_at->setTimezone($subEvent->timezone);

            $choices[$subEvent->id] = [
                'title' => $subEvent->title,
                'schedule' => $start->translatedFormat('l j F \à H\hi').' – '.$end->translatedFormat($start->isSameDay($end) ? 'H\hi' : 'l j F \à H\hi'),
                'availability' => match (true) {
                    $remaining === null => null,
                    $remaining > 1 => "{$remaining} places restantes",
                    $remaining === 1 => '1 place restante',
                    $subEvent->allow_waitlist => "Complet : liste d'attente",
                    default => 'Complet',
                },
                'closed' => $remaining === 0 && ! $subEvent->allow_waitlist,
            ];
        }

        return $choices;
    }
}
