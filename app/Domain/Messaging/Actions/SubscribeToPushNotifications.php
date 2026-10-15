<?php

declare(strict_types=1);

namespace App\Domain\Messaging\Actions;

use App\Domain\Messaging\Models\PushSubscription;

final class SubscribeToPushNotifications
{
    public function handle(int $eventId, int $registrationId, string $endpoint): PushSubscription
    {
        return PushSubscription::query()->updateOrCreate(
            ['endpoint' => $endpoint],
            ['event_id' => $eventId, 'registration_id' => $registrationId],
        );
    }
}
