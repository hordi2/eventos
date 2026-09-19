<?php

declare(strict_types=1);

namespace App\Support\Events;

use App\Domain\Event\Models\Event;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\URL;

final class EventPublicLinks
{
    public const PREVIEW_VALIDITY_DAYS = 7;

    /**
     * $formSlug : lien propre d'un formulaire de l'événement ; sans lui, le
     * lien de l'événement, qui ouvre son formulaire par défaut.
     */
    public function publicUrl(Event $event, ?string $formSlug = null): string
    {
        return route(...$this->route($event, $formSlug));
    }

    /**
     * Lien de test signé : ouvre la page publique d'un événement encore
     * « Inédit » (ResolveGuestEvent), pour le parcourir ou le faire relire
     * avant de le publier.
     */
    public function previewUrl(Event $event, ?string $formSlug = null): string
    {
        [$name, $parameters] = $this->route($event, $formSlug);

        return URL::temporarySignedRoute($name, CarbonImmutable::now()->addDays(self::PREVIEW_VALIDITY_DAYS), $parameters);
    }

    /**
     * @return array{0: string, 1: list<string>}
     */
    private function route(Event $event, ?string $formSlug): array
    {
        return $formSlug === null
            ? ['guest.registration.start', [$event->organization->slug, $event->slug]]
            : ['guest.registration.form', [$event->organization->slug, $event->slug, $formSlug]];
    }
}
