<?php

declare(strict_types=1);

namespace App\Support\Webhooks;

use App\Jobs\DeliverWebhookJob;
use App\Support\Webhooks\Models\Webhook;

/**
 * Point d'entrée unique appelé par les listeners de webhooks (un par
 * domaine émetteur, ex. App\Listeners\Webhooks\DispatchRegistrationWebhooks)
 * — jamais Domain/Form ou Support/Capacity directement, pour ne pas leur
 * faire connaître l'existence des webhooks (même raisonnement que
 * SendEmailToContact pour Messaging).
 */
final class DispatchWebhooksForEvent
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(int $organizationId, WebhookEvent $event, array $payload): void
    {
        Webhook::query()
            ->where('organization_id', $organizationId)
            ->where('is_active', true)
            ->get()
            ->filter(fn (Webhook $webhook): bool => $webhook->isSubscribedTo($event->value))
            ->each(fn (Webhook $webhook) => DeliverWebhookJob::dispatch($organizationId, $webhook->id, $event->value, $payload));
    }
}
