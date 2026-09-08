<?php

declare(strict_types=1);

namespace App\Domain\Organization\Actions;

use App\Domain\Event\Models\Event;
use App\Domain\Organization\Models\RegistrationNotificationPreference;
use App\Models\User;

final class SetRegistrationNotificationPreference
{
    public function handle(User $user, Event $event, bool $notifyCreated, bool $notifyUpdated, bool $notifyCancelled): RegistrationNotificationPreference
    {
        return RegistrationNotificationPreference::query()->updateOrCreate(
            ['user_id' => $user->id, 'event_id' => $event->id],
            [
                'organization_id' => $event->organization_id,
                'notify_created' => $notifyCreated,
                'notify_updated' => $notifyUpdated,
                'notify_cancelled' => $notifyCancelled,
            ],
        );
    }
}
