<?php

declare(strict_types=1);

use App\Domain\Contact\Models\Contact;
use App\Domain\Event\Models\Event;
use App\Domain\Form\Events\RegistrationCreated;
use App\Domain\Form\Models\RegistrationStatus;
use App\Domain\Messaging\Models\EmailAutomation;
use App\Domain\Messaging\Models\EmailAutomationStatus;
use App\Domain\Messaging\Models\EmailAutomationType;
use App\Domain\Messaging\Models\EmailTemplate;
use App\Domain\Organization\Models\MembershipRole;
use App\Mail\GenericMail;
use Illuminate\Support\Facades\Mail;

it('envoie la confirmation configurée dès qu\'une inscription est confirmée', function (): void {
    Mail::fake();
    [$organization, $admin] = organizationWithContactRole(MembershipRole::Admin);
    $event = Event::factory()->for($organization)->create();
    $contact = Contact::factory()->for($organization)->create(['email' => 'grace@example.org', 'first_name' => 'Grace']);
    $template = EmailTemplate::factory()->for($organization)->create([
        'created_by' => $admin->id,
        'blocks' => [['type' => 'text', 'html' => '<p>Merci {{first_name}}, votre place est confirmée.</p>']],
    ]);
    EmailAutomation::factory()->for($organization)->create([
        'event_id' => $event->id,
        'email_template_id' => $template->id,
        'created_by' => $admin->id,
        'type' => EmailAutomationType::Confirmation,
        'segment' => null,
        'scheduled_at' => null,
        'status' => EmailAutomationStatus::Active,
    ]);
    $registration = registerContactForEvent($organization, $event, $contact, RegistrationStatus::Confirmed);

    event(new RegistrationCreated($registration));

    Mail::assertSent(GenericMail::class, function (GenericMail $mail) use ($contact): bool {
        return $mail->hasTo($contact->email)
            && str_contains($mail->bodyHtml, 'Merci Grace')
            && $mail->icsAttachment !== null
            && $mail->unsubscribeUrl === null;
    });
});

it('n\'envoie rien pour une inscription en liste d\'attente', function (): void {
    Mail::fake();
    [$organization, $admin] = organizationWithContactRole(MembershipRole::Admin);
    $event = Event::factory()->for($organization)->create();
    $contact = Contact::factory()->for($organization)->create();
    $template = EmailTemplate::factory()->for($organization)->create(['created_by' => $admin->id]);
    EmailAutomation::factory()->for($organization)->create([
        'event_id' => $event->id,
        'email_template_id' => $template->id,
        'created_by' => $admin->id,
        'type' => EmailAutomationType::Confirmation,
        'segment' => null,
        'scheduled_at' => null,
        'status' => EmailAutomationStatus::Active,
    ]);
    $registration = registerContactForEvent($organization, $event, $contact, RegistrationStatus::Waitlisted);

    event(new RegistrationCreated($registration));

    Mail::assertNothingSent();
});

it('n\'envoie rien si aucune confirmation automatique n\'est configurée pour l\'événement', function (): void {
    Mail::fake();
    [$organization] = organizationWithContactRole(MembershipRole::Admin);
    $event = Event::factory()->for($organization)->create();
    $contact = Contact::factory()->for($organization)->create();
    $registration = registerContactForEvent($organization, $event, $contact, RegistrationStatus::Confirmed);

    event(new RegistrationCreated($registration));

    Mail::assertNothingSent();
});
