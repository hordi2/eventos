<?php

declare(strict_types=1);

namespace App\Support\Registration;

use App\Domain\Event\Models\Event;
use App\Domain\Form\Actions\GenerateAttendeeQrToken;
use App\Domain\Form\Models\Attendee;
use App\Domain\Form\Models\Registration;
use App\Domain\Form\Models\RegistrationStatus;
use Carbon\CarbonImmutable;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;

/**
 * QR d'entrée de chaque personne d'une inscription confirmée (T-032), prêts
 * à afficher : un par personne, titulaire en premier. Traverse Event (fin de
 * l'événement, pour l'expiration) et Form, d'où sa place dans Support.
 */
final class RenderAttendeeQrCodes
{
    public function __construct(
        private readonly GenerateAttendeeQrToken $generateAttendeeQrToken,
    ) {}

    /**
     * @return list<array{name: string, isPrimary: bool, image: string}>
     */
    public function handle(Event $event, Registration $registration): array
    {
        if ($registration->status !== RegistrationStatus::Confirmed) {
            return [];
        }

        // Valable jusqu'à deux jours après la fin de l'événement, jamais
        // moins de deux jours à partir d'aujourd'hui.
        $end = CarbonImmutable::parse($event->end_at);
        $expiresAt = ($end->isPast() ? CarbonImmutable::now() : $end)->addDays(2);

        return $registration->attendees()->orderBy('position')->get()
            ->map(fn (Attendee $attendee): array => [
                'name' => trim("{$attendee->first_name} {$attendee->last_name}"),
                'isPrimary' => $attendee->is_primary,
                'image' => (new Builder(
                    writer: new PngWriter,
                    data: $this->generateAttendeeQrToken->handle($attendee, $expiresAt),
                    size: 240,
                    margin: 10,
                ))->build()->getDataUri(),
            ])
            ->values()
            ->all();
    }
}
