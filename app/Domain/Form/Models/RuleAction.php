<?php

declare(strict_types=1);

namespace App\Domain\Form\Models;

/**
 * Le CDC (M2.2) prévoit aussi pré-remplir, sauter à une étape, appliquer un
 * tarif, ajouter un tag et envoyer une notification interne : toutes ces
 * actions dépendent de modules qui n'existent pas encore (Ticketing,
 * Contact, Messaging). Seules les trois actions calculables dès aujourd'hui
 * sont implémentées.
 */
enum RuleAction: string
{
    case Show = 'show';
    case Hide = 'hide';
    case Require = 'require';
}
