<?php

declare(strict_types=1);

use App\Domain\Contact\Models\Contact;
use App\Domain\Event\Models\Event;
use App\Domain\Form\Models\RegistrationStatus;
use App\Domain\Messaging\Models\MessageAutomation;
use App\Domain\Messaging\Models\MessageAutomationStatus;
use App\Domain\Messaging\Models\MessageAutomationType;
use App\Domain\Messaging\Models\MessageChannel;
use App\Domain\Messaging\Models\WhatsappTemplate;
use App\Domain\Organization\Models\MembershipRole;
use App\Jobs\SendWhatsappAutomationJob;
use App\Support\Messaging\SendWhatsappToContact;
use App\Support\Messaging\WhatsappProvider;
use App\Support\MultiTenancy\CurrentOrganization;
use App\Support\Segments\ComputeEventSegmentContacts;
use App\Support\Segments\EventSegment;

/**
 * Double de test partagé avec SendWhatsappTest — le SDK Twilio fait ses
 * propres appels HTTP (Guzzle interne), rien à "fake" côté Laravel, voir
 * le docblock de WhatsappProvider.
 */
final class FakeWhatsappAutomationProvider implements WhatsappProvider
{
    /** @var list<string> */
    public array $sentTo = [];

    public function send(string $toPhoneE164, string $contentSid, array $contentVariables, string $statusCallbackUrl): string
    {
        $this->sentTo[] = $toPhoneE164;

        return 'SM'.bin2hex(random_bytes(16));
    }
}

function runWhatsappAutomationJob(int $automationId, int $organizationId): void
{
    (new SendWhatsappAutomationJob($automationId, $organizationId))->handle(
        app(CurrentOrganization::class),
        app(ComputeEventSegmentContacts::class),
        app(SendWhatsappToContact::class),
    );
}

it('envoie uniquement aux contacts du segment ciblé, sur WhatsApp', function (): void {
    $fake = new FakeWhatsappAutomationProvider;
    $this->app->bind(WhatsappProvider::class, fn () => $fake);

    [$organization, $admin] = organizationWithContactRole(MembershipRole::Admin);
    $event = Event::factory()->for($organization)->create();
    $whatsappTemplate = WhatsappTemplate::factory()->for($organization)->create(['created_by' => $admin->id]);

    $sansReponse = Contact::factory()->for($organization)->create(['phone_e164' => '+243810000001', 'whatsapp_consent' => true]);
    $confirme = Contact::factory()->for($organization)->create(['phone_e164' => '+243810000002', 'whatsapp_consent' => true]);
    registerContactForEvent($organization, $event, $confirme, RegistrationStatus::Confirmed);

    $automation = MessageAutomation::factory()->for($organization)->create([
        'event_id' => $event->id,
        'channel' => MessageChannel::Whatsapp,
        'email_template_id' => null,
        'whatsapp_template_id' => $whatsappTemplate->id,
        'created_by' => $admin->id,
        'type' => MessageAutomationType::Invitation,
        'segment' => EventSegment::SansReponse,
        'status' => MessageAutomationStatus::Scheduled,
    ]);

    runWhatsappAutomationJob($automation->id, $organization->id);

    expect($fake->sentTo)->toBe(['+243810000001']);
    expect($automation->fresh()->status)->toBe(MessageAutomationStatus::Sent);
});

it('ne renvoie rien si l\'automatisation a été annulée avant son exécution', function (): void {
    $fake = new FakeWhatsappAutomationProvider;
    $this->app->bind(WhatsappProvider::class, fn () => $fake);

    [$organization, $admin] = organizationWithContactRole(MembershipRole::Admin);
    $event = Event::factory()->for($organization)->create();
    $whatsappTemplate = WhatsappTemplate::factory()->for($organization)->create(['created_by' => $admin->id]);
    Contact::factory()->for($organization)->create(['phone_e164' => '+243810000003', 'whatsapp_consent' => true]);

    $automation = MessageAutomation::factory()->for($organization)->create([
        'event_id' => $event->id,
        'channel' => MessageChannel::Whatsapp,
        'email_template_id' => null,
        'whatsapp_template_id' => $whatsappTemplate->id,
        'created_by' => $admin->id,
        'type' => MessageAutomationType::Invitation,
        'segment' => null,
        'status' => MessageAutomationStatus::Cancelled,
    ]);

    runWhatsappAutomationJob($automation->id, $organization->id);

    expect($fake->sentTo)->toBeEmpty();
    expect($automation->fresh()->status)->toBe(MessageAutomationStatus::Cancelled);
});
