<?php

declare(strict_types=1);

namespace App\Domain\Form\Listeners;

use App\Domain\Form\Models\Registration;
use App\Domain\Form\Models\RegistrationStatus;
use App\Support\Capacity\Events\WaitlistEntryPromoted;

/**
 * Le moteur de capacité générique (T-024) ne connaît rien de Registration :
 * il promeut une WaitlistEntry et crée un CapacityHold, un point c'est tout.
 * C'est ce listener qui fait le pont — sans lui, une place libérée fait
 * bien avancer la liste d'attente au sens du moteur de capacité, mais
 * l'inscription de la personne promue reste affichée « en liste d'attente ».
 */
final class ConfirmPromotedRegistration
{
    public function handle(WaitlistEntryPromoted $event): void
    {
        if ($event->entry->holder_type !== 'event') {
            return;
        }

        Registration::query()
            ->where('reservation_key', $event->entry->reservation_key)
            ->first()
            ?->update(['status' => RegistrationStatus::Confirmed]);
    }
}
