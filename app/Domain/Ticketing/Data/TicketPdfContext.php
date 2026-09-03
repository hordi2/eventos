<?php

declare(strict_types=1);

namespace App\Domain\Ticketing\Data;

use Carbon\CarbonImmutable;

/**
 * Attributs d'Event/Organization dont GenerateTicketPdf a besoin, portés en
 * valeurs simples plutôt que ces modèles : Domain/Ticketing ne dépend
 * jamais directement d'un modèle de Domain/Event (section 3 du CLAUDE.md).
 * À l'appelant (futur envoi de billet) de construire ce contexte.
 */
final class TicketPdfContext
{
    public function __construct(
        public readonly string $organizationName,
        public readonly string $eventTitle,
        public readonly CarbonImmutable $eventStartAt,
        public readonly string $eventTimezone,
        public readonly ?string $venueName,
        public readonly string $ticketTypeName,
        public readonly string $buyerName,
    ) {}
}
