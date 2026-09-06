<?php

declare(strict_types=1);

namespace App\Domain\Page\Data;

final class EventPageData
{
    /**
     * @param  list<array{time: string, title: string, description: ?string}>  $programItems
     * @param  list<array{question: string, answer: string}>  $faqItems
     */
    public function __construct(
        public readonly string $title,
        public readonly ?string $subtitle,
        public readonly ?string $description,
        public readonly ?string $bannerUrl,
        public readonly string $metaDescription,
        public readonly ?string $venueName,
        public readonly ?string $venueAddress,
        public readonly ?float $venueLatitude,
        public readonly ?float $venueLongitude,
        public readonly bool $isOnline,
        public readonly array $programItems,
        public readonly array $faqItems,
    ) {}
}
