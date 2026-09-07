<?php

declare(strict_types=1);

namespace App\Support\Webhooks\Actions;

use App\Models\User;
use App\Support\Webhooks\Models\Webhook;
use Illuminate\Support\Facades\Gate;

final class UpdateWebhook
{
    /**
     * @param  list<string>  $subscribedEvents
     */
    public function handle(Webhook $webhook, User $editor, string $url, array $subscribedEvents, bool $isActive): Webhook
    {
        Gate::forUser($editor)->authorize('manageIntegrations', $webhook->organization);

        $webhook->update([
            'url' => $url,
            'subscribed_events' => $subscribedEvents,
            'is_active' => $isActive,
        ]);

        return $webhook;
    }
}
