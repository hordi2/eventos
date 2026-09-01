<?php

declare(strict_types=1);

namespace App\Domain\Event\Models;

/**
 * États persistés en base : uniquement ceux qu'une action humaine
 * détermine (Créer, Publier, Archiver). "live" et "ended" ne sont jamais
 * stockés — ce sont des états dérivés de l'heure courante par rapport à
 * start_at/end_at, calculés par Event::computedStatus(). Voir la machine
 * à états complète (draft → published → live → ended → archived) décrite
 * dans le ticket T-010.
 */
enum EventStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Archived = 'archived';
}
