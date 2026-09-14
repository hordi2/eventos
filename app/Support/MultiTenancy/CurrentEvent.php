<?php

declare(strict_types=1);

namespace App\Support\MultiTenancy;

/**
 * Événement sur lequel porte la requête en cours, posé uniquement pour un
 * collaborateur (RestrictCollaboratorToSharedEvents, ResolveApiCheckInEvent).
 *
 * OrganizationPolicy s'en sert pour borner les droits d'un collaborateur à
 * son événement partagé sans modifier les dizaines d'appels existants du
 * type Gate::authorize('checkIn', $event->organization) : hors de ce
 * contexte, un collaborateur n'a aucune capacité.
 */
final class CurrentEvent
{
    private ?int $eventId = null;

    public function set(int $eventId): void
    {
        $this->eventId = $eventId;
    }

    public function clear(): void
    {
        $this->eventId = null;
    }

    public function id(): ?int
    {
        return $this->eventId;
    }
}
