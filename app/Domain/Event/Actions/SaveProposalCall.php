<?php

declare(strict_types=1);

namespace App\Domain\Event\Actions;

use App\Domain\Event\Models\Event;
use App\Domain\Event\Models\ProposalCall;
use Carbon\CarbonImmutable;

/**
 * Réglages de l'appel à contributions d'un événement (D6) : ouvert ou non,
 * texte d'appel, date limite. L'appel est créé au premier enregistrement.
 */
final class SaveProposalCall
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Event $event, array $data): ProposalCall
    {
        return ProposalCall::query()->updateOrCreate(
            ['event_id' => $event->id],
            [
                'organization_id' => $event->organization_id,
                'is_open' => (bool) ($data['is_open'] ?? false),
                'intro' => $data['intro'] ?? null,
                'closes_at' => $this->closesAt($event, $data['closes_at'] ?? null),
            ],
        );
    }

    /**
     * La date limite est saisie dans le fuseau de l'événement et stockée en
     * UTC (règle 4.3).
     */
    private function closesAt(Event $event, mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        return CarbonImmutable::parse($value, $event->timezone)->utc();
    }
}
