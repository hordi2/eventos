<?php

declare(strict_types=1);

use App\Domain\Contact\Models\Contact;
use App\Domain\Event\Models\Event;
use App\Domain\Form\Models\RegistrationStatus;
use App\Domain\Messaging\Models\EmailAutomation;
use App\Domain\Messaging\Models\EmailAutomationStatus;
use App\Domain\Messaging\Models\EmailAutomationType;
use App\Domain\Messaging\Models\EmailTemplate;
use App\Domain\Organization\Models\MembershipRole;
use App\Jobs\SendEmailAutomationJob;
use App\Mail\GenericMail;
use App\Support\Messaging\GenerateEventIcs;
use App\Support\Messaging\RenderEmailTemplate;
use App\Support\Messaging\SendEmailToContact;
use App\Support\MultiTenancy\CurrentOrganization;
use App\Support\Segments\ComputeEventSegmentContacts;
use App\Support\Segments\EventSegment;
use Illuminate\Support\Facades\Mail;

function runEmailAutomationJob(int $automationId, int $organizationId): void
{
    (new SendEmailAutomationJob($automationId, $organizationId))->handle(
        app(CurrentOrganization::class),
        app(ComputeEventSegmentContacts::class),
        app(RenderEmailTemplate::class),
        app(SendEmailToContact::class),
        app(GenerateEventIcs::class),
    );
}

it('envoie uniquement aux contacts du segment ciblé, avec l\'ICS pour une invitation', function (): void {
    Mail::fake();
    [$organization, $admin] = organizationWithContactRole(MembershipRole::Admin);
    $event = Event::factory()->for($organization)->create();
    $template = EmailTemplate::factory()->for($organization)->create(['created_by' => $admin->id]);

    $sansReponse = Contact::factory()->for($organization)->create(['email' => 'sans-reponse@example.org']);
    $confirme = Contact::factory()->for($organization)->create(['email' => 'confirme@example.org']);
    registerContactForEvent($organization, $event, $confirme, RegistrationStatus::Confirmed);

    $automation = EmailAutomation::factory()->for($organization)->create([
        'event_id' => $event->id,
        'email_template_id' => $template->id,
        'created_by' => $admin->id,
        'type' => EmailAutomationType::Invitation,
        'segment' => EventSegment::SansReponse,
        'status' => EmailAutomationStatus::Scheduled,
    ]);

    runEmailAutomationJob($automation->id, $organization->id);

    Mail::assertSent(GenericMail::class, fn (GenericMail $mail): bool => $mail->hasTo('sans-reponse@example.org') && $mail->icsAttachment !== null);
    Mail::assertNotSent(GenericMail::class, fn (GenericMail $mail): bool => $mail->hasTo('confirme@example.org'));
    expect($automation->fresh()->status)->toBe(EmailAutomationStatus::Sent);
});

it('ne renvoie rien si l\'automatisation a été annulée avant son exécution', function (): void {
    Mail::fake();
    [$organization, $admin] = organizationWithContactRole(MembershipRole::Admin);
    $event = Event::factory()->for($organization)->create();
    $template = EmailTemplate::factory()->for($organization)->create(['created_by' => $admin->id]);
    Contact::factory()->for($organization)->create();

    $automation = EmailAutomation::factory()->for($organization)->create([
        'event_id' => $event->id,
        'email_template_id' => $template->id,
        'created_by' => $admin->id,
        'type' => EmailAutomationType::Invitation,
        'segment' => null,
        'status' => EmailAutomationStatus::Cancelled,
    ]);

    runEmailAutomationJob($automation->id, $organization->id);

    Mail::assertNothingSent();
    expect($automation->fresh()->status)->toBe(EmailAutomationStatus::Cancelled);
});
