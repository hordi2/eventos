<?php

declare(strict_types=1);

namespace App\Domain\Event\Models;

/**
 * Taxonomie reprise de la bibliothèque de modèles (M1.4 du CDC), groupée
 * par catégorie : entreprise, associatif, éducation, religieux, personnel,
 * agence.
 */
enum EventType: string
{
    // Entreprise
    case Conference = 'conference';
    case ProductLaunch = 'product_launch';
    case Seminar = 'seminar';
    case GeneralAssembly = 'general_assembly';
    case Kickoff = 'kickoff';

    // Associatif
    case Gala = 'gala';
    case Fundraiser = 'fundraiser';

    // Éducation
    case Graduation = 'graduation';
    case OpenHouse = 'open_house';
    case ParentsMeeting = 'parents_meeting';

    // Religieux
    case Religious = 'religious';

    // Personnel
    case Wedding = 'wedding';
    case Birthday = 'birthday';
    case Baptism = 'baptism';
    case Memorial = 'memorial';

    // Agence / autre
    case Agency = 'agency';
    case Other = 'other';
}
