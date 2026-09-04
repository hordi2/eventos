<?php

declare(strict_types=1);

namespace App\Support\CheckIn;

use App\Domain\CheckIn\Data\BadgeContext;
use App\Domain\CheckIn\Data\GuestData;
use App\Domain\CheckIn\Models\BadgeSettings;
use App\Domain\Event\Models\Event;
use App\Domain\Ticketing\Actions\GenerateTicketQrToken;
use App\Domain\Ticketing\Models\Ticket;
use Carbon\CarbonImmutable;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Support\Facades\Storage;

/**
 * Assemble les BadgeContext d'un événement — traverse Domain/CheckIn,
 * Domain/Ticketing (jeton QR d'un billet) et le stockage du logo : vit hors
 * de ces modules pour la même raison que GetEventGuestList (voir son
 * docblock).
 *
 * QR uniquement pour les billets payés : un invité RSVP (Domain/Form) n'a
 * aucun mécanisme de QR aujourd'hui (seul T-055, Domain/Ticketing, en a
 * construit un) et le check-in RSVP se fait par recherche, jamais par scan
 * — pas de QR à générer pour lui, signalé plutôt que construit ici.
 */
final class BuildBadgeContexts
{
    public function __construct(
        private readonly GetGuestBadgeColor $getGuestBadgeColor,
        private readonly GenerateTicketQrToken $generateTicketQrToken,
    ) {}

    /**
     * @param  list<GuestData>  $guests
     * @return list<BadgeContext>
     */
    public function handle(Event $event, string $organizationName, array $guests, ?BadgeSettings $badgeSettings): array
    {
        $logoDataUri = $this->resolveLogoDataUri($badgeSettings);

        return array_map(
            fn (GuestData $guest): BadgeContext => new BadgeContext(
                organizationName: $organizationName,
                eventTitle: $event->title,
                guestName: $guest->name,
                logoDataUri: $logoDataUri,
                accentColor: $guest->guestType === 'attendee'
                    ? $this->getGuestBadgeColor->forAttendee($event->organization_id, $guest->id)
                    : null,
                qrDataUri: $guest->guestType === 'ticket' ? $this->buildTicketQrDataUri($guest->id) : null,
            ),
            $guests,
        );
    }

    private function buildTicketQrDataUri(int $ticketId): ?string
    {
        $ticket = Ticket::query()->find($ticketId);

        if ($ticket === null) {
            return null;
        }

        $token = $this->generateTicketQrToken->handle($ticket, CarbonImmutable::now()->addYear());

        return (new Builder(writer: new PngWriter, data: $token, size: 240, margin: 8))->build()->getDataUri();
    }

    private function resolveLogoDataUri(?BadgeSettings $badgeSettings): ?string
    {
        if ($badgeSettings?->logo_path === null) {
            return null;
        }

        $contents = Storage::disk('local')->get($badgeSettings->logo_path);

        if ($contents === null) {
            return null;
        }

        $mime = Storage::disk('local')->mimeType($badgeSettings->logo_path) ?: 'image/png';

        return 'data:'.$mime.';base64,'.base64_encode($contents);
    }
}
