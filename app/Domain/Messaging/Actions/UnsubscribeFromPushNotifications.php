<?php

declare(strict_types=1);

namespace App\Domain\Messaging\Actions;

use App\Domain\Messaging\Models\PushSubscription;

final class UnsubscribeFromPushNotifications
{
    public function handle(string $endpoint): void
    {
        PushSubscription::query()->where('endpoint', $endpoint)->delete();
    }
}
