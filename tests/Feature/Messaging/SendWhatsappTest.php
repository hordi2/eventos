<?php

declare(strict_types=1);

use App\Domain\Contact\Models\Contact;
use App\Domain\Messaging\Models\WhatsappMessage;
use App\Domain\Messaging\Models\WhatsappTemplate;
use App\Models\User;
use App\Support\Messaging\SendWhatsappToContact;
use App\Support\Messaging\WhatsappProvider;

/**
 * Double de test : le SDK Twilio fait ses propres appels HTTP (Guzzle
 * interne), jamais via Illuminate\Support\Facades\Http — rien à "fake"
 * côté Laravel, seule l'interface WhatsappProvider est substituable (voir
 * son propre docblock).
 */
final class FakeWhatsappProvider implements WhatsappProvider
{
    /** @var list<array{to: string, contentSid: string, contentVariables: array<int, string>}> */
    public array $sent = [];

    public function send(string $toPhoneE164, string $contentSid, array $contentVariables, string $statusCallbackUrl): string
    {
        $this->sent[] = ['to' => $toPhoneE164, 'contentSid' => $contentSid, 'contentVariables' => $contentVariables];

        return 'SM'.bin2hex(random_bytes(16));
    }
}

it('envoie un message WhatsApp à un contact éligible et journalise le message', function (): void {
    $fake = new FakeWhatsappProvider;
    $this->app->bind(WhatsappProvider::class, fn () => $fake);

    $organization = makeOrganizationForMessaging();
    $contact = Contact::factory()->for($organization)->create([
        'phone_e164' => '+243812345678',
        'whatsapp_consent' => true,
    ]);

    $whatsappTemplate = WhatsappTemplate::factory()->for($organization)->create([
        'created_by' => User::factory()->create()->id,
        'provider_template_sid' => 'HXabc123',
        'variable_mapping' => ['first_name'],
    ]);

    $message = app(SendWhatsappToContact::class)->handle($organization, $contact, $whatsappTemplate, null);

    expect($message)->not->toBeNull();
    expect($message->fresh()->status->value)->toBe('sent');
    expect($message->to_phone_e164)->toBe('+243812345678');
    expect($fake->sent)->toHaveCount(1);
    expect($fake->sent[0]['contentSid'])->toBe('HXabc123');
});

it('n\'envoie rien à un contact sans consentement WhatsApp', function (): void {
    $fake = new FakeWhatsappProvider;
    $this->app->bind(WhatsappProvider::class, fn () => $fake);

    $organization = makeOrganizationForMessaging();
    $contact = Contact::factory()->for($organization)->create(['phone_e164' => '+243812345678', 'whatsapp_consent' => false]);
    $whatsappTemplate = WhatsappTemplate::factory()->for($organization)->create([
        'created_by' => User::factory()->create()->id,
    ]);

    $result = app(SendWhatsappToContact::class)->handle($organization, $contact, $whatsappTemplate, null);

    expect($result)->toBeNull();
    expect(WhatsappMessage::query()->count())->toBe(0);
    expect($fake->sent)->toBeEmpty();
});

it('n\'envoie rien à un contact sans numéro de téléphone', function (): void {
    $fake = new FakeWhatsappProvider;
    $this->app->bind(WhatsappProvider::class, fn () => $fake);

    $organization = makeOrganizationForMessaging();
    $contact = Contact::factory()->for($organization)->create(['phone_e164' => null, 'whatsapp_consent' => true]);
    $whatsappTemplate = WhatsappTemplate::factory()->for($organization)->create([
        'created_by' => User::factory()->create()->id,
    ]);

    $result = app(SendWhatsappToContact::class)->handle($organization, $contact, $whatsappTemplate, null);

    expect($result)->toBeNull();
    expect($fake->sent)->toBeEmpty();
});

it('n\'envoie rien à un contact marqué invalide sur WhatsApp', function (): void {
    $fake = new FakeWhatsappProvider;
    $this->app->bind(WhatsappProvider::class, fn () => $fake);

    $organization = makeOrganizationForMessaging();
    $contact = Contact::factory()->for($organization)->create([
        'phone_e164' => '+243812345678',
        'whatsapp_consent' => true,
        'whatsapp_invalid_at' => now(),
        'whatsapp_invalid_reason' => 'failed',
    ]);
    $whatsappTemplate = WhatsappTemplate::factory()->for($organization)->create([
        'created_by' => User::factory()->create()->id,
    ]);

    $result = app(SendWhatsappToContact::class)->handle($organization, $contact, $whatsappTemplate, null);

    expect($result)->toBeNull();
    expect($fake->sent)->toBeEmpty();
});
