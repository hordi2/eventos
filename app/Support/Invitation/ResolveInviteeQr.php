<?php

declare(strict_types=1);

namespace App\Support\Invitation;

use App\Domain\Contact\Models\EventInvitee;
use App\Domain\Event\Models\Event;
use App\Domain\Form\Actions\GenerateAttendeeQrToken;
use App\Domain\Form\Models\Attendee;
use App\Domain\Form\Models\Registration;
use App\Domain\Form\Models\RegistrationStatus;
use Carbon\CarbonImmutable;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;

/**
 * Code QR personnel d'un invité. Inscrit : son code d'entrée, celui que
 * l'accueil scanne. Pas encore inscrit : l'adresse de son invitation, qui
 * l'amène à répondre. Dans les deux cas, il a toujours quelque chose à
 * montrer.
 *
 * Traverse Contact (l'invité), Event (l'expiration) et Form (l'inscription)
 * — sa place est dans Support (section 3 du CLAUDE.md).
 */
final class ResolveInviteeQr
{
    public function __construct(
        private readonly GenerateAttendeeQrToken $generateAttendeeQrToken,
    ) {}

    public function png(Event $event, EventInvitee $invitee, int $size = 480): string
    {
        return (new Builder(
            writer: new PngWriter,
            data: $this->data($event, $invitee),
            size: $size,
            margin: 16,
        ))->build()->getString();
    }

    /**
     * Ce que porte le code : le jeton d'entrée, ou l'adresse de
     * l'invitation.
     */
    public function data(Event $event, EventInvitee $invitee): string
    {
        $attendee = $this->attendee($event, $invitee);

        if ($attendee === null) {
            $event->loadMissing('organization');

            return route('guest.registration.invitation.open', [
                $event->organization->slug,
                $event->slug,
                $invitee->invitation_token,
            ]);
        }

        // Valable jusqu'à deux jours après la fin de l'événement, jamais
        // moins de deux jours à partir d'aujourd'hui (même règle que
        // RenderAttendeeQrCodes).
        $end = CarbonImmutable::parse($event->end_at);
        $expiresAt = ($end->isPast() ? CarbonImmutable::now() : $end)->addDays(2);

        return $this->generateAttendeeQrToken->handle($attendee, $expiresAt);
    }

    private function attendee(Event $event, EventInvitee $invitee): ?Attendee
    {
        $registration = Registration::query()
            ->where('event_id', $event->id)
            ->where('contact_id', $invitee->contact_id)
            ->whereNull('parent_registration_id')
            ->where('status', RegistrationStatus::Confirmed)
            ->latest('id')
            ->first();

        return $registration?->attendees()->where('is_primary', true)->first();
    }
}
