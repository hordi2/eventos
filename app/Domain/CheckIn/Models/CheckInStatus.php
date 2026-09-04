<?php

declare(strict_types=1);

namespace App\Domain\CheckIn\Models;

/**
 * Conflict : un scan est arrivé alors qu'un scan Accepted existait déjà
 * pour le même invité et la même direction (M7.1.4 — deux postes scannant
 * le même billet : premier accepté, second signalé en conflit).
 */
enum CheckInStatus: string
{
    case Accepted = 'accepted';
    case Conflict = 'conflict';
}
