<?php

declare(strict_types=1);

use App\Domain\Contact\Models\Contact;
use App\Domain\Event\Models\Event;
use App\Domain\Form\Events\RegistrationCreated;
use App\Domain\Form\Models\RegistrationStatus;
use App\Domain\Messaging\Models\EmailTemplate;
use App\Domain\Messaging\Models\MessageAutomation;
use App\Domain\Messaging\Models\MessageAutomationStatus;
use App\Domain\Messaging\Models\MessageAutomationType;
use App\Domain\Messaging\Models\MessageChannel;
use App\Domain\Messaging\Models\WhatsappTemplate;
use App\Domain\Organization\Models\MembershipRole;
use App\Mail\GenericMail;
use App\Support\Messaging\WhatsappProvider;
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
    MessageAutomation::factory()->for($organization)->create([
        'event_id' => $event->id,
        'email_template_id' => $template->id,
        'created_by' => $admin->id,
        'type' => MessageAutomationType::Confirmation,
        'segment' => null,
        'scheduled_at' => null,
        'status' => MessageAutomationStatus::Active,
    ]);
    $registration = registerContactForEvent($organization, $event, $contact, RegistrationStatus::Confirmed);

    event(new RegistrationCreated($registration));

    // Exactement une fois : un événement RegistrationCreated ne doit
    // déclencher qu'un seul envoi, jamais deux (bug réel trouvé pendant les
    // tests — la découverte automatique des listeners doublait
    // l'enregistrement explicite d'AppServiceProvider, voir bootstrap/app.php).
    Mail::assertSentCount(1);
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
    MessageAutomation::factory()->for($organization)->create([
        'event_id' => $event->id,
        'email_template_id' => $template->id,
        'created_by' => $admin->id,
        'type' => MessageAutomationType::Confirmation,
        'segment' => null,
        'scheduled_at' => null,
        'status' => MessageAutomationStatus::Active,
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

it('envoie la confirmation WhatsApp configurée dès qu\'une inscription est confirmée', function (): void {
    $fake = new class implements WhatsappProvider
    {
        /** @var list<array{to: string, contentSid: string}> */
        public array $sent = [];

        public function send(string $toPhoneE164, string $contentSid, array $contentVariables, string $statusCallbackUrl): string
        {
            $this->sent[] = ['to' => $toPhoneE164, 'contentSid' => $contentSid];

            return 'SM'.bin2hex(random_bytes(16));
        }
    };
    $this->app->bind(WhatsappProvider::class, fn () => $fake);

    [$organization, $admin] = organizationWithContactRole(MembershipRole::Admin);
    $event = Event::factory()->for($organization)->create();
    $contact = Contact::factory()->for($organization)->create(['phone_e164' => '+243812345678', 'whatsapp_consent' => true]);
    $whatsappTemplate = WhatsappTemplate::factory()->for($organization)->create([
        'created_by' => $admin->id,
        'provider_template_sid' => 'HXconfirm',
    ]);
    MessageAutomation::factory()->for($organization)->create([
        'event_id' => $event->id,
        'channel' => MessageChannel::Whatsapp,
        'email_template_id' => null,
        'whatsapp_template_id' => $whatsappTemplate->id,
        'created_by' => $admin->id,
        'type' => MessageAutomationType::Confirmation,
        'segment' => null,
        'scheduled_at' => null,
        'status' => MessageAutomationStatus::Active,
    ]);
    $registration = registerContactForEvent($organization, $event, $contact, RegistrationStatus::Confirmed);

    event(new RegistrationCreated($registration));

    expect($fake->sent)->toHaveCount(1);
    expect($fake->sent[0]['to'])->toBe('+243812345678');
    expect($fake->sent[0]['contentSid'])->toBe('HXconfirm');
});
