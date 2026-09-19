<?php

declare(strict_types=1);

namespace App\Domain\Messaging\Listeners;

use App\Domain\Event\Data\EventDuplicationPart;
use App\Domain\Event\Events\EventDuplicated;
use App\Domain\Messaging\Actions\CreateMessageAutomation;
use App\Domain\Messaging\Models\MessageAutomation;
use App\Domain\Messaging\Models\MessageAutomationStatus;
use App\Domain\Messaging\Models\MessageChannel;
use Carbon\CarbonImmutable;

/**
 * Recopie les e-mails et messages WhatsApp automatiques d'un événement
 * dupliqué. Un envoi daté — déjà parti ou encore à venir dans l'original —
 * est reprogrammé à sa date décalée, sauf si elle est déjà passée ; la
 * confirmation automatique reste active. Tout passe par
 * CreateMessageAutomation, qui programme les envois.
 */
final class CopyAutomationsToDuplicatedEvent
{
    public function __construct(
        private readonly CreateMessageAutomation $createMessageAutomation,
    ) {}

    public function handle(EventDuplicated $duplication): void
    {
        if (! $duplication->includes(EventDuplicationPart::Messages)) {
            return;
        }

        $now = CarbonImmutable::now();

        foreach ($duplication->eventIdMap as $sourceEventId => $copyEventId) {
            $automations = MessageAutomation::query()
                ->where('event_id', $sourceEventId)
                ->where('status', '!=', MessageAutomationStatus::Cancelled)
                ->orderBy('id')
                ->get();

            foreach ($automations as $automation) {
                $scheduledAt = $automation->type->isScheduledByDate() ? $duplication->shift($automation->scheduled_at) : null;

                if ($automation->type->isScheduledByDate() && ($scheduledAt === null || $scheduledAt->lessThanOrEqualTo($now))) {
                    continue;
                }

                $this->createMessageAutomation->handle(
                    $duplication->organization,
                    $duplication->duplicator,
                    $copyEventId,
                    $automation->channel,
                    (int) ($automation->channel === MessageChannel::Email ? $automation->email_template_id : $automation->whatsapp_template_id),
                    $automation->type,
                    $scheduledAt,
                    $automation->segment,
                );
            }
        }
    }
}
