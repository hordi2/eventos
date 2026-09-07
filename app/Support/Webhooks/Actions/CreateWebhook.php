<?php

declare(strict_types=1);

namespace App\Support\Webhooks\Actions;

use App\Domain\Organization\Models\Organization;
use App\Models\User;
use App\Support\Webhooks\Models\Webhook;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

final class CreateWebhook
{
    /**
     * @param  list<string>  $subscribedEvents
     */
    public function handle(Organization $organization, User $creator, string $url, array $subscribedEvents): Webhook
    {
        Gate::forUser($creator)->authorize('manageIntegrations', $organization);

        return Webhook::query()->create([
            'organization_id' => $organization->id,
            'url' => $url,
            'secret' => Str::random(40),
            'subscribed_events' => $subscribedEvents,
            'is_active' => true,
        ]);
    }
}
