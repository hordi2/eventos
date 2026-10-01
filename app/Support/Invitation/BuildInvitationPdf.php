<?php

declare(strict_types=1);

namespace App\Support\Invitation;

use App\Domain\Contact\Models\EventInvitee;
use App\Domain\Event\Models\Event;
use App\Domain\Event\Models\EventCategory;
use App\Domain\Form\Models\Registration;
use App\Domain\Page\Models\Page;
use App\Domain\Page\Models\PageBlockType;
use App\Domain\Page\Support\PageBlocks;
use App\Support\Events\EventPublicLinks;
use App\Support\Registration\RenderAttendeeQrCodes;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Support\Facades\Storage;

/**
 * Faire-part en PDF : la même invitation que la page web, feuillet après
 * feuillet — la couverture, puis un feuillet par bloc composé par
 * l'organisateur, avec son fond. Ce qui n'a pas de sens sur papier (compte
 * à rebours, livre d'or) ne s'imprime pas.
 *
 * Traverse Event, Page, Contact et Form : sa place est dans Support
 * (section 3 du CLAUDE.md).
 *
 * Toutes les images partent en data URI : dompdf n'a alors rien à aller
 * chercher sur le réseau, et le fichier se lit partout, hors ligne compris.
 */
final class BuildInvitationPdf
{
    /**
     * Blocs qui n'ont pas de sens imprimés : un décompte figé et un livre
     * d'or qu'on ne peut pas signer sur du papier.
     *
     * @var list<string>
     */
    private const SKIPPED = ['countdown', 'guest_book'];

    public function __construct(
        private readonly EventPublicLinks $eventPublicLinks,
        private readonly RenderAttendeeQrCodes $renderAttendeeQrCodes,
    ) {}

    public function handle(Event $event, ?EventInvitee $invitee = null, ?Registration $registration = null): string
    {
        return Pdf::loadView('guest.invitation-pdf', ['invitation' => $this->data($event, $invitee, $registration)])
            ->setPaper('a4', 'portrait')
            ->output();
    }

    public function data(Event $event, ?EventInvitee $invitee = null, ?Registration $registration = null): InvitationPdfData
    {
        $event->loadMissing(['venue', 'organization']);
        $page = Page::query()->where('event_id', $event->id)->first();
        $start = $event->start_at->setTimezone($event->timezone);
        $isPersonal = $event->type->category() === EventCategory::Personal;
        $rsvpUrl = $this->rsvpUrl($event, $invitee);

        return new InvitationPdfData(
            title: $event->title,
            subtitle: $event->subtitle,
            description: $event->description,
            eyebrow: $page?->cover_eyebrow ?: ($isPersonal ? 'Vous êtes invité' : 'Invitation'),
            script: $page?->cover_script,
            monogram: $page?->cover_monogram,
            coverOverlay: min(90, max(0, $page === null ? 50 : $page->cover_overlay)) / 100,
            day: $start->format('d'),
            month: mb_strtoupper($start->translatedFormat('F')),
            shortMonth: mb_strtoupper($start->translatedFormat('M')),
            year: $start->format('Y'),
            fullDate: $start->translatedFormat('l j F Y'),
            time: $start->format('H\hi'),
            place: $event->is_online ? 'En ligne' : $event->venue?->name,
            address: $event->is_online ? $event->online_url : $event->venue?->address,
            guestName: $this->guestName($invitee, $registration),
            coverImage: $this->image($page?->banner_path),
            logoImage: $this->image($event->organization->logo_path),
            entryQr: $this->entryQr($event, $registration),
            entryNote: $registration === null
                ? null
                : "Ce code QR est à présenter à l'accueil. Votre ponctualité nous fera plaisir.",
            rsvpUrl: $rsvpUrl,
            rsvpQr: $this->qr($rsvpUrl, 320),
            rsvpLabel: $isPersonal ? 'Répondre à l\'invitation' : 'S\'inscrire',
            calendar: $this->calendar($start),
            blocks: $this->blocks($page, $event),
        );
    }

    /**
     * Les blocs de la page, dans leur ordre, prêts à imprimer : les images
     * sont résolues en data URI et les blocs sans objet sur papier sont
     * écartés.
     *
     * @return list<array<string, mixed>>
     */
    private function blocks(?Page $page, Event $event): array
    {
        $blocks = [];

        foreach (PageBlocks::resolve($page) as $block) {
            $type = $block['type'];

            if (in_array($type, self::SKIPPED, true)) {
                continue;
            }

            if ($type === PageBlockType::Venue->value && ($event->is_online || $event->venue === null)) {
                continue;
            }

            $blocks[] = [
                ...$block,
                'backgroundImage' => $this->image($block['background'] ?? null),
                'overlay' => min(90, max(0, (int) ($block['backgroundOverlay'] ?? 45))) / 100,
                'onDark' => ($block['textTone'] ?? 'dark') === 'light',
                'image' => $this->image($block['path'] ?? null),
                'photos' => $this->photos($block),
            ];
        }

        return $blocks;
    }

    /**
     * Photos d'une galerie, en data URI. Six au plus : au-delà, le fichier
     * devient trop lourd pour être envoyé par WhatsApp.
     *
     * @param  array<string, mixed>  $block
     * @return list<array{image: string, description: ?string}>
     */
    private function photos(array $block): array
    {
        if (($block['type'] ?? null) !== PageBlockType::Gallery->value) {
            return [];
        }

        $photos = [];

        foreach (array_slice($block['items'] ?? [], 0, 6) as $item) {
            $image = $this->image($item['path'] ?? null);

            if ($image !== null) {
                $photos[] = ['image' => $image, 'description' => $item['description'] ?? null];
            }
        }

        return $photos;
    }

    /**
     * Le mois de l'événement, semaine par semaine, pour le calendrier du
     * feuillet « Save the date ». Les cases vides valent null.
     *
     * @return array{weeks: list<list<?int>>, highlight: int}
     */
    private function calendar(CarbonImmutable $start): array
    {
        $offset = (int) $start->startOfMonth()->dayOfWeekIso - 1;
        $daysInMonth = (int) $start->daysInMonth;
        $weeks = [];

        for ($week = 0; $week < (int) ceil(($offset + $daysInMonth) / 7); $week++) {
            $days = [];

            for ($weekday = 0; $weekday < 7; $weekday++) {
                $day = $week * 7 + $weekday - $offset + 1;
                $days[] = $day >= 1 && $day <= $daysInMonth ? $day : null;
            }

            $weeks[] = $days;
        }

        return ['weeks' => $weeks, 'highlight' => (int) $start->day];
    }

    /**
     * Lien de réponse : celui de l'invité quand il en a un — il ouvre son
     * invitation sans qu'il ait à se faire reconnaître.
     */
    private function rsvpUrl(Event $event, ?EventInvitee $invitee): string
    {
        if ($invitee === null) {
            return $this->eventPublicLinks->publicUrl($event);
        }

        return route('guest.registration.invitation.open', [
            $event->organization->slug,
            $event->slug,
            $invitee->invitation_token,
        ]);
    }

    private function guestName(?EventInvitee $invitee, ?Registration $registration): ?string
    {
        if ($registration !== null) {
            $name = trim("{$registration->first_name} {$registration->last_name}");

            return $name === '' ? null : $name;
        }

        $contact = $invitee?->contact;

        return $contact === null ? null : $contact->fullName();
    }

    /**
     * QR d'entrée du titulaire de l'inscription. Sans inscription
     * confirmée, il n'y a rien à présenter à la porte : le feuillet
     * « Votre entrée » ne s'imprime pas.
     */
    private function entryQr(Event $event, ?Registration $registration): ?string
    {
        if ($registration === null) {
            return null;
        }

        $codes = $this->renderAttendeeQrCodes->handle($event, $registration);

        return $codes === [] ? null : $codes[0]['image'];
    }

    private function qr(string $data, int $size): string
    {
        return (new Builder(writer: new PngWriter, data: $data, size: $size, margin: 10))->build()->getDataUri();
    }

    /**
     * Image du disque public, en data URI. Absente du disque : rien, et la
     * mise en page tient sans elle.
     */
    private function image(?string $path): ?string
    {
        if ($path === null) {
            return null;
        }

        $disk = Storage::disk('public');

        if (! $disk->exists($path)) {
            return null;
        }

        return 'data:'.($disk->mimeType($path) ?: 'image/jpeg').';base64,'.base64_encode((string) $disk->get($path));
    }
}
