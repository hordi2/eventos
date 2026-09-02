<?php

declare(strict_types=1);

namespace App\Domain\Contact\Models;

/**
 * M3.2 du CDC : « un contact peut appartenir à un foyer (famille) ou à un
 * groupe (entreprise, délégation) » — même structure de regroupement,
 * seule l'étiquette diffère.
 */
enum HouseholdType: string
{
    case Family = 'family';
    case Group = 'group';
}
