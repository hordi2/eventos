<?php

declare(strict_types=1);

namespace App\Domain\Event\Models;

/**
 * Segment choisi par l'organisateur à la création de l'événement (demande
 * utilisateur) : indépendant de la taxonomie détaillée `EventType`
 * (bibliothèque de modèles, M1.4) — sert uniquement à orienter l'organisateur
 * vers l'onglet pertinent de la page « Mise à niveau ».
 */
enum EventAudience: string
{
    case Professional = 'professional';
    case Community = 'community';
    case Personal = 'personal';

    public function label(): string
    {
        return match ($this) {
            self::Professional => 'Événement professionnel',
            self::Community => 'Événement communautaire',
            self::Personal => 'Événement personnel',
        };
    }
}
