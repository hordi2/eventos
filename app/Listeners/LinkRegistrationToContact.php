<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Domain\Contact\Actions\FindOrCreateContact;
use App\Domain\Form\Events\RegistrationCreated;

/**
 * Ni Domain/Form ni Domain/Contact ne doivent se référencer l'un l'autre
 * (section 3 du CLAUDE.md) : ce pont vit donc en dehors des deux, au niveau
 * application — pas dans Domain/Contact/Listeners ni Domain/Form/Listeners.
 *
 * Remplace enfin le contournement pris à T-030 (identité portée directement
 * sur Registration, faute de Contact) : chaque inscription reste liée à sa
 * fiche contact, réutilisable d'un événement à l'autre (critère de T-040).
 */
final class LinkRegistrationToContact
{
    public function __construct(
        private readonly FindOrCreateContact $findOrCreateContact,
    ) {}

    public function handle(RegistrationCreated $event): void
    {
        $registration = $event->registration;

        if ($registration->contact_id !== null) {
            return;
        }

        $contact = $this->findOrCreateContact->handle(
            $registration->organization_id,
            $registration->email,
            $registration->first_name,
            $registration->last_name,
            $registration->phone_e164,
        );

        $registration->update(['contact_id' => $contact->id]);
    }
}
