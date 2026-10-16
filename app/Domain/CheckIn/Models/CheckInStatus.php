<?php

declare(strict_types=1);

namespace App\Domain\CheckIn\Models;

/**
 * Conflict : un scan est arrivé alors qu'un scan Accepted existait déjà
 * pour le même invité et la même direction (M7.1.4 — deux postes scannant
 * le même billet : premier accepté, second signalé en conflit).
 *
 * Rejected : un jeton QR fourni avec le scan n'a pas passé la vérification
 * (signature, expiration, statut ou non-réutilisation, §4.6 CLAUDE.md) —
 * aucune ligne CheckIn n'est créée pour ce statut, voir SyncCheckIns.
 */
enum CheckInStatus: string
{
    case Accepted = 'accepted';
    case Conflict = 'conflict';
    case Rejected = 'rejected';
}
