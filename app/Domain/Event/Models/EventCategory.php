<?php

declare(strict_types=1);

namespace App\Domain\Event\Models;

/**
 * Regroupement en deux catégories, plus large que les six de la taxonomie
 * M1.4 (voir EventType) — sert à conditionner des règles simples comme le
 * numéro de téléphone obligatoire sur le RSVP pour un événement personnel.
 */
enum EventCategory
{
    case Corporate;
    case Personal;
}
