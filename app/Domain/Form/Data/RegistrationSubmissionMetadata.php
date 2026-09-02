<?php

declare(strict_types=1);

namespace App\Domain\Form\Data;

/**
 * Capture de la source d'une inscription (M2.4 du CDC), purement
 * informative — jamais utilisée pour une décision métier.
 */
final class RegistrationSubmissionMetadata
{
    /**
     * @param  array<string, string>|null  $utm
     */
    public function __construct(
        public readonly ?string $source = null,
        public readonly ?array $utm = null,
        public readonly ?string $referrer = null,
        public readonly ?string $ipAddress = null,
        public readonly ?string $userAgent = null,
        public readonly ?string $locale = null,
    ) {}
}
