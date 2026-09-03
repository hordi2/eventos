<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Domain\Contact\Models\Contact;
use App\Domain\Event\Models\Event;
use App\Domain\Form\Events\RegistrationCreated;
use App\Domain\Form\Models\RegistrationStatus;
use App\Domain\Messaging\Models\MessageAutomation;
use App\Domain\Messaging\Models\MessageAutomationStatus;
use App\Domain\Messaging\Models\MessageAutomationType;
use App\Domain\Messaging\Models\MessageChannel;
use App\Domain\Organization\Models\Organization;
use App\Support\Messaging\SendWhatsappToContact;

/**
 * Équivalent WhatsApp de SendConfirmationEmail — voir son docblock pour le
 * choix d'un listener synchrone (pas ShouldQueue) et l'ordre d'exécution
 * requis après LinkRegistrationToContact.
 */
final class SendConfirmationWhatsapp
{
    public function __construct(
        private readonly SendWhatsappToContact $sendWhatsappToContact,
    ) {}

    public function handle(RegistrationCreated $registrationCreated): void
    {
        $registration = $registrationCreated->registration;

        if ($registration->status !== RegistrationStatus::Confirmed || $registration->contact_id === null) {
            return;
        }

        $automation = MessageAutomation::query()
            ->where('event_id', $registration->event_id)
            ->where('type', MessageAutomationType::Confirmation)
            ->where('status', MessageAutomationStatus::Active)
            ->where('channel', MessageChannel::Whatsapp)
            ->with('whatsappTemplate')
            ->first();

        if ($automation === null) {
            return;
        }

        $contact = Contact::query()->find($registration->contact_id);

        if ($contact === null) {
            return;
        }

        $organization = Organization::query()->findOrFail($registration->organization_id);
        $event = Event::query()->findOrFail($registration->event_id);

        $this->sendWhatsappToContact->handle($organization, $contact, $automation->whatsappTemplate, $event);
    }
}
