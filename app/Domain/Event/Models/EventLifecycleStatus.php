<?php

declare(strict_types=1);

namespace App\Domain\Event\Models;

/**
 * Machine à états complète du CDC : draft → published → live → ended →
 * archived. Jamais stockée telle quelle — calculée par
 * Event::computedStatus() à partir du EventStatus persisté et de l'heure
 * courante dans le fuseau de l'événement.
 */
enum EventLifecycleStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Live = 'live';
    case Ended = 'ended';
    case Archived = 'archived';
}
