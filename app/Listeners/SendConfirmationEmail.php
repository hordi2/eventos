<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Domain\Contact\Models\Contact;
use App\Domain\Event\Models\Event;
use App\Domain\Form\Events\RegistrationCreated;
use App\Domain\Form\Models\RegistrationStatus;
use App\Domain\Messaging\Models\EmailAutomation;
use App\Domain\Messaging\Models\EmailAutomationStatus;
use App\Domain\Messaging\Models\EmailAutomationType;
use App\Domain\Organization\Models\Organization;
use App\Support\Messaging\GenerateEventIcs;
use App\Support\Messaging\RenderEmailTemplate;
use App\Support\Messaging\SendEmailToContact;

/**
 * Ni Domain/Form ni Domain/Messaging ne doivent se référencer l'un l'autre
 * (section 3 du CLAUDE.md) : ce pont vit hors des deux, comme
 * LinkRegistrationToContact. Volontairement synchrone (pas ShouldQueue) :
 * l'événement RegistrationCreated porte un modèle Registration cloisonné
 * par organisation — le sérialiser pour une file d'attente le ferait
 * re-résoudre par son global scope avant que CurrentOrganization ne soit
 * repositionné (même piège que ProcessContactImportJob, T-041). Rester
 * synchrone l'évite complètement, sans rien coûter en performance : le
 * travail réellement lourd (envoi SMTP) est déjà mis en file par
 * SendEmail → SendEmailMessageJob, largement dans le budget des 60 s du
 * critère d'acceptation.
 *
 * Doit toujours s'exécuter après LinkRegistrationToContact (voir l'ordre
 * d'enregistrement dans AppServiceProvider) : c'est lui qui renseigne
 * contact_id.
 */
final class SendConfirmationEmail
{
    public function __construct(
        private readonly RenderEmailTemplate $renderEmailTemplate,
        private readonly SendEmailToContact $sendEmailToContact,
        private readonly GenerateEventIcs $generateEventIcs,
    ) {}

    public function handle(RegistrationCreated $registrationCreated): void
    {
        $registration = $registrationCreated->registration;

        if ($registration->status !== RegistrationStatus::Confirmed || $registration->contact_id === null) {
            return;
        }

        $automation = EmailAutomation::query()
            ->where('event_id', $registration->event_id)
            ->where('type', EmailAutomationType::Confirmation)
            ->where('status', EmailAutomationStatus::Active)
            ->with('emailTemplate')
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
        $ics = $this->generateEventIcs->handle($event);

        $this->sendEmailToContact->handle(
            $organization,
            $contact,
            $this->renderEmailTemplate->renderSubject($automation->emailTemplate, $contact, $event),
            $this->renderEmailTemplate->render($automation->emailTemplate, $contact, $event),
            true,
            $ics,
        );
    }
}
