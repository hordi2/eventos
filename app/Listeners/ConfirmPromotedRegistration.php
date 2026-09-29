<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Domain\Event\Models\Event;
use App\Domain\Form\Models\Registration;
use App\Domain\Form\Models\RegistrationStatus;
use App\Support\Capacity\Events\WaitlistEntryPromoted;

/**
 * Le moteur de capacité générique (T-024) ne connaît rien de Registration :
 * il promeut une WaitlistEntry et crée un CapacityHold, un point c'est tout.
 * C'est ce listener qui fait le pont — sans lui, une place libérée fait
 * bien avancer la liste d'attente au sens du moteur de capacité, mais
 * l'inscription de la personne promue reste affichée « en liste d'attente ».
 *
 * Vit hors de Domain/Form (comme SendConfirmationEmail) depuis qu'il lit le
 * réglage « validation manuelle » de l'événement : personne n'est confirmé
 * sans l'accord de l'organisateur quand il l'a demandé.
 */
final class ConfirmPromotedRegistration
{
    public function handle(WaitlistEntryPromoted $event): void
    {
        if ($event->entry->holder_type !== 'event') {
            return;
        }

        $registration = Registration::query()->where('reservation_key', $event->entry->reservation_key)->first();

        if ($registration === null) {
            return;
        }

        $registration->update(['status' => $this->promotedStatus($registration)]);
    }

    /**
     * Une session suit son inscription principale ; une inscription
     * principale suit le réglage de l'événement.
     */
    private function promotedStatus(Registration $registration): RegistrationStatus
    {
        if ($registration->parent_registration_id !== null) {
            $parent = Registration::query()->find($registration->parent_registration_id);

            return $parent?->status === RegistrationStatus::Pending ? RegistrationStatus::Pending : RegistrationStatus::Confirmed;
        }

        $requiresApproval = (bool) Event::query()->whereKey($registration->event_id)->value('requires_approval');

        return $requiresApproval ? RegistrationStatus::Pending : RegistrationStatus::Confirmed;
    }
}
