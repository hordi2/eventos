<?php

declare(strict_types=1);

namespace App\Support\Invitation;

/**
 * Le faire-part tel qu'il s'imprime (D1) : la même invitation que la page
 * web, dans le même ordre — la couverture, puis un feuillet par bloc
 * composé par l'organisateur, avec le fond qu'il lui a donné.
 */
final class InvitationPdfData
{
    /**
     * @param  array{weeks: list<list<?int>>, highlight: int}  $calendar
     * @param  list<array<string, mixed>>  $blocks  blocs imprimables, images déjà en data URI
     */
    public function __construct(
        public readonly string $title,
        public readonly ?string $subtitle,
        public readonly ?string $description,
        public readonly string $eyebrow,
        public readonly ?string $script,
        public readonly ?string $monogram,
        public readonly float $coverOverlay,
        public readonly string $day,
        public readonly string $month,
        public readonly string $shortMonth,
        public readonly string $year,
        public readonly string $fullDate,
        public readonly string $weekday,
        public readonly string $time,
        public readonly ?string $place,
        public readonly ?string $address,
        public readonly ?string $guestName,
        public readonly ?string $coverImage,
        public readonly ?string $logoImage,
        public readonly ?string $entryQr,
        public readonly ?string $entryNote,
        public readonly string $rsvpUrl,
        public readonly string $rsvpQr,
        public readonly string $rsvpLabel,
        // « Je ne pourrai pas venir » ne s'imprime que si le formulaire
        // accepte un refus : sinon la réponse n'existerait nulle part.
        public readonly bool $declineEnabled,
        public readonly array $calendar,
        public readonly array $blocks,
        // Les lettres de la page web, portées sur le papier.
        public readonly string $fontFaces,
        public readonly string $headingFamily,
        public readonly string $bodyFamily,
        public readonly string $scriptFamily,
    ) {}
}
