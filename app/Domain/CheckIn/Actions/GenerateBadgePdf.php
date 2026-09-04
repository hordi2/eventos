<?php

declare(strict_types=1);

namespace App\Domain\CheckIn\Actions;

use App\Domain\CheckIn\Data\BadgeContext;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * Badge individuel à la demande (T-064, AC : « génération d'un badge en
 * < 2 s ») — format carte, imprimable directement, pas de mise en page en
 * planche (voir GenerateBadgeSheetPdf pour la génération en masse).
 */
final class GenerateBadgePdf
{
    public function handle(BadgeContext $context): string
    {
        return Pdf::loadView('badges.single', ['context' => $context])
            ->setPaper([0, 0, 269.29, 184.25])
            ->output();
    }
}
