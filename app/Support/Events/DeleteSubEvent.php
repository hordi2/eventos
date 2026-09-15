<?php

declare(strict_types=1);

namespace App\Support\Events;

use App\Domain\Event\Actions\DeleteEvent;
use App\Domain\Event\CannotDeleteEventException;
use App\Domain\Event\Models\Event;
use App\Domain\Form\Models\Registration;
use App\Domain\Form\Models\RegistrationStatus;
use App\Models\User;

/**
 * Suppression d'un événement secondaire (T-013, critère M1.3) : refusée tant
 * que des invités y sont inscrits ou en liste d'attente, jamais silencieuse.
 * Vit dans Support car elle lit Domain/Form avant d'agir sur Domain/Event.
 */
final class DeleteSubEvent
{
    public function __construct(
        private readonly DeleteEvent $deleteEvent,
    ) {}

    public function handle(Event $subEvent, User $deleter): void
    {
        $hasActiveRegistrations = Registration::query()
            ->where('event_id', $subEvent->id)
            ->whereIn('status', [RegistrationStatus::Confirmed->value, RegistrationStatus::Waitlisted->value])
            ->exists();

        if ($hasActiveRegistrations) {
            throw CannotDeleteEventException::hasRegistrations();
        }

        $this->deleteEvent->handle($subEvent, $deleter);
    }
}
