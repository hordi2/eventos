<?php

declare(strict_types=1);

use App\Domain\Contact\Models\Contact;
use App\Domain\Messaging\Models\EmailMessage;
use App\Mail\GenericMail;
use App\Support\Messaging\SendEmailToContact;
use Illuminate\Support\Facades\Mail;

it('envoie un e-mail à un contact éligible et journalise le message', function (): void {
    Mail::fake();
    $organization = makeOrganizationForMessaging();
    $contact = Contact::factory()->for($organization)->create(['email' => 'grace@example.org']);

    $emailMessage = app(SendEmailToContact::class)->handle($organization, $contact, 'Bienvenue', '<p>Bonjour</p>');

    expect($emailMessage)->not->toBeNull();
    // La ligne renvoyée par SendEmail reflète l'état au moment de la mise
    // en file (queued) : c'est SendEmailMessageJob, une instance à part,
    // qui la fait passer à "sent" — fresh() relit l'état réel en base.
    expect($emailMessage->fresh()->status->value)->toBe('sent');
    expect($emailMessage->to_email)->toBe('grace@example.org');
    Mail::assertSent(GenericMail::class, fn (GenericMail $mail): bool => $mail->hasTo('grace@example.org'));
});

it('n\'envoie rien à un contact désabonné et ne journalise aucun message', function (): void {
    Mail::fake();
    $organization = makeOrganizationForMessaging();
    $contact = Contact::factory()->for($organization)->create(['email' => 'grace@example.org', 'unsubscribed_at' => now()]);

    $result = app(SendEmailToContact::class)->handle($organization, $contact, 'Bienvenue', '<p>Bonjour</p>');

    expect($result)->toBeNull();
    expect(EmailMessage::query()->count())->toBe(0);
    Mail::assertNothingSent();
});

it('n\'envoie rien à un contact marqué invalide par un bounce dur', function (): void {
    Mail::fake();
    $organization = makeOrganizationForMessaging();
    $contact = Contact::factory()->for($organization)->create([
        'email' => 'grace@example.org',
        'email_invalid_at' => now(),
        'email_invalid_reason' => 'hard_bounce',
    ]);

    $result = app(SendEmailToContact::class)->handle($organization, $contact, 'Bienvenue', '<p>Bonjour</p>');

    expect($result)->toBeNull();
    Mail::assertNothingSent();
});

it('inclut un lien de désabonnement uniquement sur les e-mails non transactionnels', function (): void {
    Mail::fake();
    $organization = makeOrganizationForMessaging();
    $contact = Contact::factory()->for($organization)->create(['email' => 'grace@example.org']);

    app(SendEmailToContact::class)->handle($organization, $contact, 'Newsletter', '<p>Bonjour</p>', isTransactional: false);
    app(SendEmailToContact::class)->handle($organization, $contact, 'Confirmation', '<p>Bonjour</p>', isTransactional: true);

    $sentMails = collect();
    Mail::assertSent(GenericMail::class, function (GenericMail $mail) use (&$sentMails): bool {
        $sentMails->push($mail);

        return true;
    });

    $newsletter = $sentMails->firstWhere('mailSubject', 'Newsletter');
    $confirmation = $sentMails->firstWhere('mailSubject', 'Confirmation');

    expect($newsletter->unsubscribeUrl)->not->toBeNull();
    expect($confirmation->unsubscribeUrl)->toBeNull();
});
