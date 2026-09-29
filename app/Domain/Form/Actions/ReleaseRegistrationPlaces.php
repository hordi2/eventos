<?php

declare(strict_types=1);

namespace App\Domain\Form\Actions;

use App\Domain\Form\Models\Registration;
use App\Domain\Form\Models\RegistrationAnswer;
use App\Domain\Form\Support\OptionReservationKey;
use App\Support\Capacity\Actions\ReleaseCapacity;

/**
 * Rend tout ce qu'une inscription tenait : ses places sur l'événement — ce
 * qui promeut le premier de la liste d'attente (T-024) —, les quotas
 * d'option encore tenus, titulaire et accompagnants compris, ses sessions ;
 * et révoque les QR de chaque personne. Commun à l'annulation par l'invité
 * et au refus par l'organisateur ; à appeler dans leur transaction.
 */
final class ReleaseRegistrationPlaces
{
    public function __construct(
        private readonly ReleaseCapacity $releaseCapacity,
        private readonly SyncSubEventRegistrations $syncSubEventRegistrations,
    ) {}

    public function handle(Registration $registration): void
    {
        $this->releaseCapacity->handle('event', (string) $registration->event_id, $registration->reservation_key);

        $registration->allAnswers()->with('formField.options')->get()
            ->each(function (RegistrationAnswer $answer) use ($registration): void {
                $this->releaseSelectedOptions($registration, $answer);
            });

        $registration->attendees()->update(['qr_jti' => null]);

        // Les sessions d'événements secondaires suivent l'inscription principale.
        $this->syncSubEventRegistrations->handle($registration, []);
    }

    private function releaseSelectedOptions(Registration $registration, RegistrationAnswer $answer): void
    {
        $field = $answer->formField;

        if (! $field->type->supportsOptions()) {
            return;
        }

        $selected = is_array($answer->value) ? $answer->value : [$answer->value];
        $attendeeId = $answer->attendee_id !== null ? (int) $answer->attendee_id : null;

        foreach ($selected as $value) {
            $option = $field->options->firstWhere('value', $value);

            if ($option !== null && $option->quota !== null) {
                $this->releaseCapacity->handle('form_field_option', (string) $option->id, OptionReservationKey::for($registration->reservation_key, $option->id, $attendeeId));
            }
        }
    }
}
