<?php

declare(strict_types=1);

namespace App\Domain\Organization\Models;

/**
 * Accès d'un collaborateur à UN événement partagé (Paramètres → Partage
 * d'événements). Rien au niveau de l'organisation : un collaborateur ne voit
 * ni les contacts, ni la facturation, ni les autres événements.
 */
enum CollaboratorPermission: string
{
    case None = 'none';
    case CheckIn = 'check_in';
    case Administrator = 'administrator';

    public function label(): string
    {
        return match ($this) {
            self::None => 'Aucun accès',
            self::CheckIn => 'Lecture seule + check-in',
            self::Administrator => 'Administrateur',
        };
    }

    /**
     * Capacités de la matrice OrganizationPolicy accordées, uniquement tant
     * que la requête porte sur l'événement partagé (CurrentEvent).
     *
     * Administrateur a tous les droits sur son événement, suppression et
     * remboursements compris (décision produit). createEvents y figure pour
     * créer le formulaire de l'événement : la création d'un nouvel
     * événement, elle, ne porte sur aucun événement existant et reste donc
     * refusée par RestrictCollaboratorToSharedEvents.
     *
     * @return list<string>
     */
    public function abilities(): array
    {
        return match ($this) {
            self::None => [],
            self::CheckIn => ['viewGuests', 'checkIn'],
            self::Administrator => [
                'createEvents',
                'updateEvents',
                'deleteEvents',
                'viewGuests',
                'updateGuests',
                'sendCommunications',
                'checkIn',
                'manageTicketing',
                'viewFinancials',
                'exportData',
                'refundTickets',
            ],
        };
    }

    /**
     * Page d'arrivée d'un événement partagé : l'éditeur n'est pas ouvert à
     * la lecture seule, qui y recevrait un 403.
     */
    public function eventUrl(int $eventId): string
    {
        return $this === self::Administrator
            ? route('events.edit', $eventId)
            : route('events.dashboard.index', $eventId);
    }
}
