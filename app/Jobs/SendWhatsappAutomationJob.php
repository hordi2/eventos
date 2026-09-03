<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\Contact\Models\Contact;
use App\Domain\Event\Models\Event;
use App\Domain\Messaging\Models\MessageAutomation;
use App\Domain\Messaging\Models\MessageAutomationStatus;
use App\Domain\Messaging\Models\MessageChannel;
use App\Domain\Organization\Models\Organization;
use App\Support\Messaging\SendWhatsappToContact;
use App\Support\MultiTenancy\CurrentOrganization;
use App\Support\Segments\ComputeEventSegmentContacts;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Équivalent WhatsApp de SendEmailAutomationJob — même mécanique
 * d'idempotence et de résolution d'ID (voir son docblock). Pas d'ICS
 * joint ici, contrairement à l'e-mail : un modèle WhatsApp déjà approuvé
 * (accord explicite) ne peut pas porter de pièce jointe libre.
 */
final class SendWhatsappAutomationJob implements ShouldQueue
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
        SendWhatsappToContact $sendWhatsappToContact,
    ): void {
        $currentOrganization->set($this->organizationId);

        $automation = MessageAutomation::query()->with('whatsappTemplate')->find($this->automationId);

        if ($automation === null || $automation->status !== MessageAutomationStatus::Scheduled || $automation->channel !== MessageChannel::Whatsapp) {
            return;
        }

        $organization = Organization::query()->findOrFail($this->organizationId);
        $event = Event::query()->findOrFail($automation->event_id);
        $template = $automation->whatsappTemplate;

        $contacts = $automation->segment !== null
            ? $computeEventSegmentContacts->query($event, $automation->segment)->get()
            : Contact::query()->where('organization_id', $organization->id)->get();

        foreach ($contacts as $contact) {
            $sendWhatsappToContact->handle($organization, $contact, $template, $event);
        }

        $automation->update(['status' => MessageAutomationStatus::Sent, 'sent_at' => CarbonImmutable::now()]);
    }
}
