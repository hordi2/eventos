<?php

declare(strict_types=1);

namespace App\Support\Events;

use App\Domain\Event\Models\Event;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\URL;

final class EventPublicLinks
{
    public const PREVIEW_VALIDITY_DAYS = 7;

    public function publicUrl(Event $event): string
    {
        return route('guest.registration.start', [$event->organization->slug, $event->slug]);
    }

    /**
     * Lien de test signé : ouvre la page publique d'un événement encore
     * « Inédit » (ResolveGuestEvent), pour le parcourir ou le faire relire
     * avant de le publier.
     */
    public function previewUrl(Event $event): string
    {
        return URL::temporarySignedRoute(
            'guest.registration.start',
            CarbonImmutable::now()->addDays(self::PREVIEW_VALIDITY_DAYS),
            [$event->organization->slug, $event->slug],
        );
    }
}
