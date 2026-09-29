<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Domain\Event\Models\Event;
use App\Domain\Form\Events\RegistrationApproved;
use App\Support\Registration\OpenRegistrationDonation;

/**
 * Don promis dans le formulaire par un invité dont l'inscription attendait
 * la validation : sa commande n'est ouverte qu'une fois l'inscription
 * acceptée — personne ne règle un don pour une place qui pourrait être
 * refusée.
 */
final class OpenDonationOnApprovedRegistration
{
    public function __construct(
        private readonly OpenRegistrationDonation $openRegistrationDonation,
    ) {}

    public function handle(RegistrationApproved $approved): void
    {
        $registration = $approved->registration;
        $this->openRegistrationDonation->handle(Event::query()->findOrFail($registration->event_id), $registration);
    }
}
