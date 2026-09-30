<?php

declare(strict_types=1);

namespace App\Support\Invitation;

/**
 * Le faire-part tel qu'il s'imprime (D1) : les mêmes éléments que la page
 * web, dans le même ordre, pour qu'un invité reconnaisse l'un dans l'autre.
 */
final class InvitationPdfData
{
    /**
     * @param  list<array{time: ?string, title: string, description: ?string}>  $programme
     */
    public function __construct(
        public readonly string $title,
        public readonly ?string $subtitle,
        public readonly ?string $description,
        public readonly string $eyebrow,
        public readonly string $day,
        public readonly string $month,
        public readonly string $year,
        public readonly string $fullDate,
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
        public readonly array $programme,
    ) {}
}
