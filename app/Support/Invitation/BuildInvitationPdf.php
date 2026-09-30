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
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Support\Facades\Storage;

/**
 * Faire-part en PDF : la même invitation que la page web, page après page —
 * couverture, l'essentiel, le programme, l'entrée et la réponse. Traverse
 * Event, Page, Contact et Form : sa place est dans Support (section 3 du
 * CLAUDE.md).
 *
 * Toutes les images partent en data URI : dompdf n'a alors rien à aller
 * chercher sur le réseau, et le fichier se lit partout, hors ligne compris.
 */
final class BuildInvitationPdf
{
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
            eyebrow: $isPersonal ? 'Vous êtes invité' : 'Invitation',
            day: $start->format('d'),
            month: mb_strtoupper($start->translatedFormat('F')),
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
            programme: $this->programme($page),
        );
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
     * confirmée, il n'y a rien à présenter à la porte : la page « Votre
     * entrée » ne s'imprime pas.
     */
    private function entryQr(Event $event, ?Registration $registration): ?string
    {
        if ($registration === null) {
            return null;
        }

        $codes = $this->renderAttendeeQrCodes->handle($event, $registration);

        return $codes === [] ? null : $codes[0]['image'];
    }

    /**
     * @return list<array{time: ?string, title: string, description: ?string}>
     */
    private function programme(?Page $page): array
    {
        $items = [];

        foreach (PageBlocks::resolve($page) as $block) {
            if (($block['type'] ?? null) !== PageBlockType::Program->value) {
                continue;
            }

            foreach ($block['items'] ?? [] as $item) {
                $title = is_string($item['title'] ?? null) ? $item['title'] : '';

                if ($title !== '') {
                    $items[] = [
                        'time' => is_string($item['time'] ?? null) ? $item['time'] : null,
                        'title' => $title,
                        'description' => is_string($item['description'] ?? null) ? $item['description'] : null,
                    ];
                }
            }
        }

        return $items;
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
