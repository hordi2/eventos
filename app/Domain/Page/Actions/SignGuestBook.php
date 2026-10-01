<?php

declare(strict_types=1);

namespace App\Domain\Page\Actions;

use App\Domain\Event\Models\Event;
use App\Domain\Page\Models\GuestBookMessage;

/**
 * Mot laissé par un invité dans le livre d'or (D1). Publié aussitôt :
 * c'est ce qui en fait la joie, et l'organisateur peut masquer d'un geste.
 */
final class SignGuestBook
{
    public function handle(Event $event, string $authorName, string $message, ?string $authorIp = null): GuestBookMessage
    {
        return GuestBookMessage::query()->create([
            'organization_id' => $event->organization_id,
            'event_id' => $event->id,
            'author_name' => $authorName,
            'message' => $message,
            'is_published' => true,
            'author_ip' => $authorIp,
        ]);
    }
}
