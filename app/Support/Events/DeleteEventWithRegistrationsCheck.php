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
 * Suppression d'un événement de premier niveau depuis le tableau de bord :
 * refusée tant que des invités y sont inscrits ou en liste d'attente, jamais
 * silencieuse — même garde que DeleteSubEvent (critère M1.3), généralisée
 * ici à tout événement. Vit dans Support car elle lit Domain/Form avant
 * d'agir sur Domain/Event (section 3 du CLAUDE.md).
 */
final class DeleteEventWithRegistrationsCheck
{
    public function __construct(
        private readonly DeleteEvent $deleteEvent,
    ) {}

    public function handle(Event $event, User $deleter): void
    {
        $hasActiveRegistrations = Registration::query()
            ->where('event_id', $event->id)
            ->whereIn('status', [RegistrationStatus::Confirmed->value, RegistrationStatus::Pending->value, RegistrationStatus::Waitlisted->value])
            ->exists();

        if ($hasActiveRegistrations) {
            throw CannotDeleteEventException::hasRegistrations();
        }

        $this->deleteEvent->handle($event, $deleter);
    }
}
