<?php

declare(strict_types=1);

use App\Domain\Contact\Models\Contact;
use App\Domain\Messaging\Models\WhatsappMessage;
use App\Domain\Messaging\Models\WhatsappWebhookEvent;
use App\Domain\Organization\Models\Organization;
use App\Support\MultiTenancy\CurrentOrganization;
use Illuminate\Testing\TestResponse;
use Twilio\Security\RequestValidator;

beforeEach(function (): void {
    config(['services.twilio.auth_token' => 'test-auth-token']);
});

/**
 * @param  array<string, string>  $payload
 */
function postSignedTwilioWebhook(array $payload): TestResponse
{
    $url = route('webhooks.twilio-whatsapp');
    $signature = (new RequestValidator('test-auth-token'))->computeSignature($url, $payload);

    return test()->withHeader('X-Twilio-Signature', $signature)->post($url, $payload);
}

it('refuse une requête sans signature Twilio valide', function (): void {
    $this->post(route('webhooks.twilio-whatsapp'), ['MessageSid' => 'SM1', 'MessageStatus' => 'delivered'])
        ->assertForbidden();
});

it('marque un message livré puis lu', function (): void {
    $organization = Organization::factory()->create();
    app(CurrentOrganization::class)->set($organization);
    $whatsappMessage = WhatsappMessage::factory()->for($organization)->sent()->create(['provider_message_id' => 'SM1']);

    postSignedTwilioWebhook(['MessageSid' => 'SM1', 'MessageStatus' => 'delivered'])->assertOk();
    expect($whatsappMessage->fresh()->status->value)->toBe('delivered');
    expect($whatsappMessage->fresh()->delivered_at)->not->toBeNull();

    postSignedTwilioWebhook(['MessageSid' => 'SM1', 'MessageStatus' => 'read'])->assertOk();
    expect($whatsappMessage->fresh()->status->value)->toBe('read');
    expect($whatsappMessage->fresh()->read_at)->not->toBeNull();
});

it('marque un contact invalide sur un échec, mais pas le contact d\'un message livré', function (): void {
    $organization = Organization::factory()->create();
    app(CurrentOrganization::class)->set($organization);
    $contact = Contact::factory()->for($organization)->create();
    $failed = WhatsappMessage::factory()->for($organization)->sent()->create(['provider_message_id' => 'SM-fail', 'contact_id' => $contact->id]);

    postSignedTwilioWebhook([
        'MessageSid' => 'SM-fail',
        'MessageStatus' => 'failed',
        'ErrorCode' => '63016',
        'ErrorMessage' => 'Numéro non joignable sur WhatsApp',
    ])->assertOk();

    expect($failed->fresh()->status->value)->toBe('failed');
    expect($contact->fresh()->whatsapp_invalid_at)->not->toBeNull();
    expect($contact->fresh()->whatsapp_invalid_reason)->toBe('Numéro non joignable sur WhatsApp');

    $otherContact = Contact::factory()->for($organization)->create();
    $delivered = WhatsappMessage::factory()->for($organization)->sent()->create(['provider_message_id' => 'SM-ok', 'contact_id' => $otherContact->id]);
    postSignedTwilioWebhook(['MessageSid' => 'SM-ok', 'MessageStatus' => 'delivered'])->assertOk();

    expect($otherContact->fresh()->whatsapp_invalid_at)->toBeNull();
});

it('traite un événement de façon idempotente : un doublon ne se traite qu\'une fois', function (): void {
    $organization = Organization::factory()->create();
    app(CurrentOrganization::class)->set($organization);
    $whatsappMessage = WhatsappMessage::factory()->for($organization)->sent()->create(['provider_message_id' => 'SM-dup']);

    $payload = ['MessageSid' => 'SM-dup', 'MessageStatus' => 'delivered'];
    postSignedTwilioWebhook($payload)->assertOk();
    postSignedTwilioWebhook($payload)->assertOk();

    expect(WhatsappWebhookEvent::query()->where('provider', 'twilio')->count())->toBe(1);
});

it('ignore silencieusement un webhook pour un message inconnu', function (): void {
    $organization = Organization::factory()->create();
    app(CurrentOrganization::class)->set($organization);

    postSignedTwilioWebhook(['MessageSid' => 'inconnu', 'MessageStatus' => 'delivered'])->assertOk();

    expect(WhatsappMessage::query()->count())->toBe(0);
});
