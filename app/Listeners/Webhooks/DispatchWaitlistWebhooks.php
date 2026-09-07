<?php

declare(strict_types=1);

namespace App\Listeners\Webhooks;

use App\Support\Capacity\Events\WaitlistEntryPromoted;
use App\Support\Webhooks\DispatchWebhooksForEvent;
use App\Support\Webhooks\WebhookEvent;

final class DispatchWaitlistWebhooks
{
    public function __construct(
        private readonly DispatchWebhooksForEvent $dispatchWebhooksForEvent,
    ) {}

    public function handle(WaitlistEntryPromoted $event): void
    {
        $entry = $event->entry;

        $this->dispatchWebhooksForEvent->handle($entry->organization_id, WebhookEvent::WaitlistPromoted, [
            'waitlist_entry_id' => $entry->id,
            'holder_type' => $entry->holder_type,
            'holder_id' => $entry->holder_id,
            'reservation_key' => $entry->reservation_key,
        ]);
    }
}
