<?php

declare(strict_types=1);

namespace App\Domain\CheckIn\Data;

/**
 * Attributs portés en valeurs simples plutôt que par les modèles d'Event/
 * Organization/Contact : Domain/CheckIn ne dépend jamais directement d'un
 * modèle d'un autre module (section 3 du CLAUDE.md) — même raisonnement
 * que TicketPdfContext (Domain/Ticketing, T-055).
 */
final class BadgeContext
{
    public function __construct(
        public readonly string $organizationName,
        public readonly string $eventTitle,
        public readonly string $guestName,
        public readonly ?string $logoDataUri,
        public readonly ?string $accentColor,
        public readonly ?string $qrDataUri,
    ) {}
}
