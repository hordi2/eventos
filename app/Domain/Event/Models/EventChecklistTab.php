<?php

declare(strict_types=1);

namespace App\Domain\Event\Models;

/**
 * Onglets de la liste de contrôle, dans l'ordre où l'organisateur les
 * traverse : préparer, ouvrir les inscriptions, gérer le jour J, conclure.
 */
enum EventChecklistTab: string
{
    case Personalize = 'personnaliser';
    case Launch = 'lancement';
    case Organize = 'organiser';
    case FollowUp = 'suivi';

    public function label(): string
    {
        return match ($this) {
            self::Personalize => 'Personnaliser',
            self::Launch => 'Lancement',
            self::Organize => 'Organiser',
            self::FollowUp => 'Suivi',
        };
    }
}
