<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\Contact\Models\Contact;
use App\Domain\Event\Models\Event;
use App\Domain\Messaging\Models\MessageAutomation;
use App\Domain\Messaging\Models\MessageAutomationStatus;
use App\Domain\Messaging\Models\MessageChannel;
use App\Domain\Organization\Models\Organization;
use App\Support\Messaging\GenerateEventIcs;
use App\Support\Messaging\RenderEmailTemplate;
use App\Support\Messaging\SendEmailToContact;
use App\Support\MultiTenancy\CurrentOrganization;
use App\Support\Segments\ComputeEventSegmentContacts;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Envoi en masse d'une automatisation planifiée sur le canal e-mail (T-045 :
 * invitation, rappel de réponse, rappel J-7/J-1, remerciement) — voir
 * SendWhatsappAutomationJob pour le même mécanisme côté WhatsApp. Reçoit
 * des ID, jamais les modèles : même piège que ProcessContactImportJob
 * (T-041) — SerializesModels re-résoudrait MessageAutomation via son
 * global scope avant que CurrentOrganization ne soit positionné.
 *
 * Idempotent (§4.4 du CLAUDE.md) : si le statut n'est plus "scheduled" à
 * l'exécution — déjà traité, ou annulé entre-temps
 * (CancelMessageAutomation) — le job ne fait rien. C'est aussi tout le
 * mécanisme d'annulation : pas besoin de retrouver ni de supprimer le job
 * Redis déjà en attente.
 */
final class SendEmailAutomationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(
        public readonly int $automationId,
        private readonly int $organizationId,
    ) {}

    public function handle(
        CurrentOrganization $currentOrganization,
        ComputeEventSegmentContacts $computeEventSegmentContacts,
        RenderEmailTemplate $renderEmailTemplate,
        SendEmailToContact $sendEmailToContact,
        GenerateEventIcs $generateEventIcs,
    ): void {
        $currentOrganization->set($this->organizationId);

        $automation = MessageAutomation::query()->with('emailTemplate')->find($this->automationId);

        if ($automation === null || $automation->status !== MessageAutomationStatus::Scheduled || $automation->channel !== MessageChannel::Email) {
            return;
        }

        $organization = Organization::query()->findOrFail($this->organizationId);
        $event = Event::query()->findOrFail($automation->event_id);
        $template = $automation->emailTemplate;

        $contacts = $automation->segment !== null
            ? $computeEventSegmentContacts->query($event, $automation->segment)->get()
            : Contact::query()->where('organization_id', $organization->id)->get();

        $ics = $automation->type->includesIcsAttachment() ? $generateEventIcs->handle($event) : null;

        foreach ($contacts as $contact) {
            $sendEmailToContact->handle(
                $organization,
                $contact,
                $renderEmailTemplate->renderSubject($template, $contact, $event),
                $renderEmailTemplate->render($template, $contact, $event),
                false,
                $ics,
            );
        }

        $automation->update(['status' => MessageAutomationStatus::Sent, 'sent_at' => CarbonImmutable::now()]);
    }
}
