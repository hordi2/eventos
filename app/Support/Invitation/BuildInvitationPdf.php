<?php

declare(strict_types=1);

namespace App\Support\Invitation;

use App\Domain\Contact\Models\EventInvitee;
use App\Domain\Event\Models\Event;
use App\Domain\Event\Models\EventCategory;
use App\Domain\Form\Models\Form;
use App\Domain\Form\Models\Registration;
use App\Domain\Page\Models\Page;
use App\Domain\Page\Models\PageBlockType;
use App\Domain\Page\Support\PageBlocks;
use App\Support\Events\EventPublicLinks;
use App\Support\Events\PresentEventSessions;
use App\Support\Events\PresentEventSpeakers;
use App\Support\Images\CropImageToRatio;
use App\Support\Page\ResolvePageMedia;
use App\Support\Registration\DeclineAnswer;
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

    /**
     * Ce qu'une feuille A4 peut porter de texte : 166 mm de large sur
     * 240 mm de haut, au corps employé par le faire-part.
     */
    private const BODY_PER_SHEET = 1500;

    private const SAVE_THE_DATE_PER_SHEET = 420;

    public function __construct(
        private readonly EventPublicLinks $eventPublicLinks,
        private readonly RenderAttendeeQrCodes $renderAttendeeQrCodes,
        private readonly ResolvePageMedia $resolvePageMedia,
        private readonly CropImageToRatio $cropImageToRatio,
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
        $families = PdfFonts::families($page?->heading_font, $page?->body_font, $page?->script_font);

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
            weekday: $start->translatedFormat('l'),
            time: $start->format('H\hi'),
            place: $event->is_online ? 'En ligne' : $event->venue?->name,
            address: $event->is_online ? $event->online_url : $event->venue?->address,
            guestName: $this->guestName($invitee, $registration),
            coverImage: $this->image($page?->banner_path, 210, 297),
            logoImage: $this->image($event->organization->logo_path),
            entryQr: $this->entryQr($event, $registration),
            entryNote: $registration === null
                ? null
                : "Ce code QR est à présenter à l'accueil. Votre ponctualité nous fera plaisir.",
            rsvpUrl: $rsvpUrl,
            rsvpQr: $this->qr($rsvpUrl, 320),
            rsvpLabel: $isPersonal ? 'Répondre à l\'invitation' : 'S\'inscrire',
            declineEnabled: $this->declineEnabled($event),
            calendar: $this->calendar($start),
            blocks: $this->blocks($page, $event),
            fontFaces: PdfFonts::faces($page?->heading_font, $page?->body_font, $page?->script_font),
            headingFamily: $families['heading'],
            bodyFamily: $families['body'],
            scriptFamily: $families['script'],
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

        $composed = PageBlocks::withClosingSheets(PageBlocks::resolve($page));

        foreach ($this->resolvePageMedia->handle($composed, $event) as $block) {
            $type = $block['type'];

            if (in_array($type, self::SKIPPED, true)) {
                continue;
            }

            if ($type === PageBlockType::Venue->value && ($event->is_online || $event->venue === null)) {
                continue;
            }

            // Un bloc que l'événement laisse vide ne devient pas une page
            // blanche — même règle que sur la page web (GetEventPage).
            if ($type === PageBlockType::Speakers->value && app(PresentEventSpeakers::class)->handle($event) === []) {
                continue;
            }

            if ($type === PageBlockType::Sessions->value && app(PresentEventSessions::class)->handle($event) === []) {
                continue;
            }

            $sheet = [
                ...$block,
                'backgroundImage' => $this->image($block['background'] ?? null, 210, 297),
                'overlay' => min(90, max(0, (int) ($block['backgroundOverlay'] ?? 45))) / 100,
                'onDark' => ($block['textTone'] ?? 'dark') === 'light',
                'image' => $block['type'] === PageBlockType::FullPhoto->value
                    ? $this->image($block['path'] ?? null, 210, 297)
                    : $this->image($block['path'] ?? null),
                'photos' => $this->photos($block),
                'items' => $this->programItems($block),
            ];

            $blocks = [...$blocks, ...$this->paginate($sheet)];
        }

        return $blocks;
    }

    /**
     * Le formulaire accepte-t-il un refus ? La page web ne montre le bouton
     * « Je ne pourrai pas venir » que dans ce cas ; le papier suit.
     */
    private function declineEnabled(Event $event): bool
    {
        $form = Form::query()
            ->where('event_id', $event->id)
            ->orderByDesc('is_default')
            ->first();

        return DeclineAnswer::isOffered($event, $form);
    }

    /**
     * Un bloc plus long qu'une feuille A4 se poursuit sur la suivante. Sans
     * cela il serait coupé net : la feuille mesure exactement 297 mm et
     * dompdf ne fait pas passer un contenu positionné d'une page à l'autre.
     * Chaque feuille de suite garde le fond du bloc et porte « (suite) ».
     *
     * @param  array<string, mixed>  $block
     * @return list<array<string, mixed>>
     */
    private function paginate(array $block): array
    {
        $perSheet = match ($block['type']) {
            // Avec une illustration, chaque moment prend plus de hauteur.
            PageBlockType::Program->value => ($block['showIcons'] ?? false) ? 7 : 9,
            PageBlockType::Faq->value, PageBlockType::Details->value => 6,
            default => null,
        };

        if ($perSheet !== null) {
            $items = $block['items'] ?? [];

            if (count($items) <= $perSheet) {
                return [$block];
            }

            $chunks = array_chunk($items, $perSheet);

            return array_map(
                fn (int $index, array $chunk): array => [...$block, 'items' => $chunk, 'continued' => $index > 0],
                array_keys($chunks),
                $chunks,
            );
        }

        $isSaveTheDate = $block['type'] === PageBlockType::SaveTheDate->value;

        if (! $isSaveTheDate && $block['type'] !== PageBlockType::Text->value) {
            return [$block];
        }

        // Le « Save the date » porte déjà son calendrier et sa grande date :
        // il reste peu de place pour le texte sur cette première feuille.
        $first = $isSaveTheDate ? self::SAVE_THE_DATE_PER_SHEET : self::BODY_PER_SHEET;
        $chunks = $this->paragraphChunks((string) ($block['body'] ?? ''), $first);

        if (count($chunks) <= 1) {
            return [$block];
        }

        $opening = array_shift($chunks);
        $sheets = [[...$block, 'body' => $opening]];

        // La suite se lit sur des feuilles de texte : réimprimer le
        // calendrier à chacune n'aurait pas de sens.
        foreach ($this->paragraphChunks(implode("\n\n", $chunks), self::BODY_PER_SHEET) as $chunk) {
            $sheets[] = [
                ...$block,
                'type' => PageBlockType::Text->value,
                'title' => $isSaveTheDate ? null : ($block['title'] ?? null),
                'body' => $chunk,
                'continued' => true,
            ];
        }

        return $sheets;
    }

    /**
     * Le texte découpé en feuilles, paragraphe par paragraphe. Un
     * paragraphe à lui seul plus long qu'une feuille se coupe à la limite
     * d'un mot, jamais au milieu.
     *
     * @return list<string>
     */
    private function paragraphChunks(string $body, int $limit): array
    {
        $paragraphs = [];

        foreach (preg_split('/\R\s*\R/', trim($body)) ?: [] as $paragraph) {
            $paragraph = trim($paragraph);

            if ($paragraph === '') {
                continue;
            }

            $paragraphs = [...$paragraphs, ...explode("\n", wordwrap($paragraph, $limit, "\n"))];
        }

        $chunks = [];
        $current = '';

        foreach ($paragraphs as $paragraph) {
            $candidate = $current === '' ? $paragraph : $current."\n\n".$paragraph;

            if (mb_strlen($candidate) > $limit && $current !== '') {
                $chunks[] = $current;
                $current = $paragraph;

                continue;
            }

            $current = $candidate;
        }

        if ($current !== '') {
            $chunks[] = $current;
        }

        return $chunks;
    }

    /**
     * Les moments du programme, chacun avec son illustration déposée en
     * data URI quand il en porte une. Les autres blocs gardent leurs
     * lignes telles quelles.
     *
     * @param  array<string, mixed>  $block
     * @return list<array<string, mixed>>
     */
    private function programItems(array $block): array
    {
        $items = $block['items'] ?? [];

        if (($block['type'] ?? null) !== PageBlockType::Program->value) {
            return $items;
        }

        return array_map(
            fn (array $item): array => [...$item, 'iconImage' => $this->image($item['path'] ?? null)],
            $items,
        );
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
            $image = $this->image($item['path'] ?? null, 70, 52);

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
    private function image(?string $path, ?int $ratioWidth = null, ?int $ratioHeight = null): ?string
    {
        if ($path === null) {
            return null;
        }

        $disk = Storage::disk('public');

        if (! $disk->exists($path)) {
            return null;
        }

        $contents = (string) $disk->get($path);

        if ($ratioWidth !== null && $ratioHeight !== null) {
            // Recadré au centre, comme la page web le ferait (object-cover).
            $cropped = $this->cropImageToRatio->handle($contents, $ratioWidth, $ratioHeight);

            if ($cropped !== null) {
                return 'data:image/jpeg;base64,'.base64_encode($cropped);
            }
        }

        return 'data:'.($disk->mimeType($path) ?: 'image/jpeg').';base64,'.base64_encode($contents);
    }
}
