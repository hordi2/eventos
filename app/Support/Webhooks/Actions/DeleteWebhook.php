<?php

declare(strict_types=1);

namespace App\Support\Webhooks\Actions;

use App\Models\User;
use App\Support\Webhooks\Models\Webhook;
use Illuminate\Support\Facades\Gate;

final class DeleteWebhook
{
    public function handle(Webhook $webhook, User $user): void
    {
        Gate::forUser($user)->authorize('manageIntegrations', $webhook->organization);

        $webhook->delete();
    }
}
