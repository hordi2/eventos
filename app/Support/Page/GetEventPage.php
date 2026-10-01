<?php

declare(strict_types=1);

namespace App\Support\Page;

use App\Domain\Event\Models\Event;
use App\Domain\Page\Data\EventPageData;
use App\Domain\Page\Models\GuestBookMessage;
use App\Domain\Page\Models\Page;
use App\Domain\Page\Support\PageBlocks;
use App\Support\Events\PresentEventSessions;
use App\Support\Events\PresentEventSpeakers;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Traverse Event (Domain/Event), Page (Domain/Page) et Organization
 * (Domain/Organization, pour le logo/la couleur de la charte graphique,
 * T-073) : ne peut vivre dans aucun des trois (section 3 du CLAUDE.md),
 * même raisonnement que GetSeatingPlan. Un événement sans ligne Page
 * (jamais personnalisé par l'organisateur) reçoit tout de même une page
 * complète, avec des blocs programme/FAQ vides et une méta-description
 * dérivée de la description.
 */
final class GetEventPage
{
    /**
     * Livre d'or : les mots publiés, les plus récents d'abord. Les dates
     * s'affichent dans le fuseau de l'événement (règle 4.3).
     *
     * @return list<array{author: string, message: string, writtenAt: string}>
     */
    private function guestBook(Event $event): array
    {
        return GuestBookMessage::query()
            ->where('event_id', $event->id)
            ->published()
            ->latest('id')
            ->limit(100)
            ->get()
            ->map(fn (GuestBookMessage $message): array => [
                'author' => $message->author_name,
                'message' => $message->message,
                'writtenAt' => $message->created_at?->setTimezone($event->timezone)->translatedFormat('j F Y') ?? '',
            ])
            ->values()
            ->all();
    }

    public function handle(Event $event): EventPageData
    {
        $event->loadMissing(['venue', 'organization']);
        $page = Page::query()->where('event_id', $event->id)->first();

        return new EventPageData(
            title: $event->title,
            subtitle: $event->subtitle,
            description: $event->description,
            bannerUrl: $page !== null && $page->banner_path !== null ? Storage::disk('public')->url($page->banner_path) : null,
            coverEyebrow: $page?->cover_eyebrow,
            coverScript: $page?->cover_script,
            coverMonogram: $page?->cover_monogram,
            // Un voile par défaut à mi-chemin : la photo reste lisible, le
            // texte aussi, quelle que soit l'image déposée.
            coverOverlay: $page === null ? 50 : $page->cover_overlay,
            coverCtaLabel: $page?->cover_cta_label,
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
            blocks: PageBlocks::resolve($page),
            speakers: app(PresentEventSpeakers::class)->handle($event),
            sessions: app(PresentEventSessions::class)->handle($event),
            guestBookMessages: $this->guestBook($event),
            organizationLogoUrl: $event->organization->logo_path !== null
                ? Storage::disk('public')->url($event->organization->logo_path)
                : null,
            organizationPrimaryColor: $event->organization->primary_color,
        );
    }
}
