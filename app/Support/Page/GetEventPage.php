<?php

declare(strict_types=1);

namespace App\Support\Page;

use App\Domain\Event\Models\Event;
use App\Domain\Page\Data\EventPageData;
use App\Domain\Page\Models\Page;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Traverse Event (Domain/Event) et Page (Domain/Page) : ne peut pas vivre
 * dans l'un ou l'autre (section 3 du CLAUDE.md), même raisonnement que
 * GetSeatingPlan. Un événement sans ligne Page (jamais personnalisé par
 * l'organisateur) reçoit tout de même une page complète, avec des blocs
 * programme/FAQ vides et une méta-description dérivée de la description.
 */
final class GetEventPage
{
    public function handle(Event $event): EventPageData
    {
        $event->loadMissing('venue');
        $page = Page::query()->where('event_id', $event->id)->first();

        return new EventPageData(
            title: $event->title,
            subtitle: $event->subtitle,
            description: $event->description,
            bannerUrl: $page !== null && $page->banner_path !== null ? Storage::disk('public')->url($page->banner_path) : null,
            metaDescription: $page !== null && $page->meta_description !== null
                ? $page->meta_description
                : Str::limit(strip_tags((string) $event->description), 155),
            venueName: $event->is_online ? null : $event->venue?->name,
            venueAddress: $event->is_online ? null : $event->venue?->address,
            // Venue::latitude/longitude sont castées "decimal:N" (chaînes,
            // pour préserver la précision — jamais de float directement en
            // base), donc une conversion explicite est nécessaire ici.
            venueLatitude: ! $event->is_online && $event->venue?->latitude !== null ? (float) $event->venue->latitude : null,
            venueLongitude: ! $event->is_online && $event->venue?->longitude !== null ? (float) $event->venue->longitude : null,
            isOnline: $event->is_online,
            programItems: $page !== null ? $page->program_items : [],
            faqItems: $page !== null ? $page->faq_items : [],
        );
    }
}
