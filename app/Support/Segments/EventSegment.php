<?php

declare(strict_types=1);

namespace App\Support\Segments;

/**
 * Les 5 segments prédéfinis du CDC (M3.4, T-042). "Sans réponse" est le
 * complément des 4 autres côté Registration — une inscription en liste
 * d'attente n'apparaît volontairement dans aucun des 5 (elle a bien
 * répondu, mais ni confirmée ni déclinée : hors du périmètre demandé par
 * le ticket, qui ne liste que ces cinq segments).
 */
enum EventSegment: string
{
    case SansReponse = 'sans_reponse';
    case Confirmes = 'confirmes';
    case Declines = 'declines';
    case Presents = 'presents';
    case NoShow = 'no_show';

    public function label(): string
    {
        return match ($this) {
            self::SansReponse => 'Sans réponse',
            self::Confirmes => 'Confirmés',
            self::Declines => 'Déclinés',
            self::Presents => 'Présents',
            self::NoShow => 'No-show',
        };
    }
}
