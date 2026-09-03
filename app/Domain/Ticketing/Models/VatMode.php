<?php

declare(strict_types=1);

namespace App\Domain\Ticketing\Models;

/**
 * Régime de TVA d'un type de billet (M5.1 du CDC : « TVA paramétrable,
 * incluse ou en sus »). None = pas de TVA applicable (ex. association loi
 * 1901, structure exonérée) — distinct de Excluded à 0 % pour ne pas avoir à
 * afficher une ligne TVA vide sur chaque billet.
 */
enum VatMode: string
{
    case None = 'none';
    case Included = 'included';
    case Excluded = 'excluded';
}
