<?php

declare(strict_types=1);

namespace App\Domain\Page\Data;

final class EventPageData
{
    /**
     * @param  list<array{time: string, title: string, description: ?string}>  $programItems
     * @param  list<array{question: string, answer: string}>  $faqItems
     * @param  list<array<string, mixed>>  $blocks  page composée par l'organisateur, dans son ordre
     * @param  list<array<string, mixed>>  $speakers  fiches des intervenants (D6)
     * @param  list<array<string, mixed>>  $sessions  programme, session par session (D6)
     * @param  list<array{author: string, message: string, writtenAt: string}>  $guestBookMessages  livre d'or, les plus récents d'abord
     */
    public function __construct(
        public readonly string $title,
        public readonly ?string $subtitle,
        public readonly ?string $description,
        public readonly ?string $bannerUrl,
        public readonly ?string $coverEyebrow,
        public readonly ?string $coverScript,
        public readonly ?string $coverMonogram,
        public readonly int $coverOverlay,
        public readonly ?string $coverCtaLabel,
        public readonly string $headingFont,
        public readonly string $bodyFont,
        public readonly string $scriptFont,
        public readonly ?string $fontStylesheet,
        public readonly string $metaDescription,
        public readonly ?string $venueName,
        public readonly ?string $venueAddress,
        public readonly ?float $venueLatitude,
        public readonly ?float $venueLongitude,
        public readonly bool $isOnline,
        public readonly array $programItems,
        public readonly array $faqItems,
        public readonly array $blocks,
        public readonly array $speakers,
        public readonly array $sessions,
        public readonly array $guestBookMessages,
        public readonly ?string $organizationLogoUrl,
        public readonly ?string $organizationPrimaryColor,
    ) {}
}
