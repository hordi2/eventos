<?php

declare(strict_types=1);

namespace App\Domain\CheckIn\Actions;

use App\Domain\CheckIn\Data\BadgeContext;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * Génération en masse (T-064, AC : « planche Avery », « repères de
 * découpe ») : 8 badges par page A4 (2 colonnes x 4 lignes), une planche
 * par tranche de 8 invités.
 */
final class GenerateBadgeSheetPdf
{
    private const PER_PAGE = 8;

    /**
     * @param  list<BadgeContext>  $contexts
     */
    public function handle(array $contexts): string
    {
        return Pdf::loadView('badges.sheet', [
            'pages' => array_chunk($contexts, self::PER_PAGE),
        ])->output();
    }
}
