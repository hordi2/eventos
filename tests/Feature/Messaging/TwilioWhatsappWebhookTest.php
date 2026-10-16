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
function postSignedTwilioWebhook(array $payload, int $organizationId): TestResponse
{
    $url = route('webhooks.twilio-whatsapp', ['organization' => $organizationId]);
    $signature = (new RequestValidator('test-auth-token'))->computeSignature($url, $payload);

    return test()->withHeader('X-Twilio-Signature', $signature)->post($url, $payload);
}

it('refuse une requête sans signature Twilio valide', function (): void {
    $organization = Organization::factory()->create();

    $this->post(route('webhooks.twilio-whatsapp', ['organization' => $organization->id]), ['MessageSid' => 'SM1', 'MessageStatus' => 'delivered'])
        ->assertForbidden();
});

it('marque un message livré puis lu', function (): void {
    $organization = Organization::factory()->create();
    app(CurrentOrganization::class)->set($organization);
    $whatsappMessage = WhatsappMessage::factory()->for($organization)->sent()->create(['provider_message_id' => 'SM1']);

    // Un vrai rappel Twilio arrive sans aucune organisation déjà posée :
    // le contexte doit se déduire de l'URL de rappel, jamais d'un résidu
    // laissé par la mise en place du test.
    app(CurrentOrganization::class)->clear();

    postSignedTwilioWebhook(['MessageSid' => 'SM1', 'MessageStatus' => 'delivered'], $organization->id)->assertOk();
    app(CurrentOrganization::class)->set($organization);
    expect($whatsappMessage->fresh()->status->value)->toBe('delivered');
    expect($whatsappMessage->fresh()->delivered_at)->not->toBeNull();
    app(CurrentOrganization::class)->clear();

    postSignedTwilioWebhook(['MessageSid' => 'SM1', 'MessageStatus' => 'read'], $organization->id)->assertOk();
    app(CurrentOrganization::class)->set($organization);
    expect($whatsappMessage->fresh()->status->value)->toBe('read');
    expect($whatsappMessage->fresh()->read_at)->not->toBeNull();
});

it('marque un contact invalide sur un échec, mais pas le contact d\'un message livré', function (): void {
    $organization = Organization::factory()->create();
    app(CurrentOrganization::class)->set($organization);
    $contact = Contact::factory()->for($organization)->create();
    $failed = WhatsappMessage::factory()->for($organization)->sent()->create(['provider_message_id' => 'SM-fail', 'contact_id' => $contact->id]);
    $otherContact = Contact::factory()->for($organization)->create();
    $delivered = WhatsappMessage::factory()->for($organization)->sent()->create(['provider_message_id' => 'SM-ok', 'contact_id' => $otherContact->id]);
    app(CurrentOrganization::class)->clear();

    postSignedTwilioWebhook([
        'MessageSid' => 'SM-fail',
        'MessageStatus' => 'failed',
        'ErrorCode' => '63016',
        'ErrorMessage' => 'Numéro non joignable sur WhatsApp',
    ], $organization->id)->assertOk();

    app(CurrentOrganization::class)->set($organization);
    expect($failed->fresh()->status->value)->toBe('failed');
    expect($contact->fresh()->whatsapp_invalid_at)->not->toBeNull();
    expect($contact->fresh()->whatsapp_invalid_reason)->toBe('Numéro non joignable sur WhatsApp');
    app(CurrentOrganization::class)->clear();

    postSignedTwilioWebhook(['MessageSid' => 'SM-ok', 'MessageStatus' => 'delivered'], $organization->id)->assertOk();

    app(CurrentOrganization::class)->set($organization);
    expect($otherContact->fresh()->whatsapp_invalid_at)->toBeNull();
});

it('traite un événement de façon idempotente : un doublon ne se traite qu\'une fois', function (): void {
    $organization = Organization::factory()->create();
    app(CurrentOrganization::class)->set($organization);
    WhatsappMessage::factory()->for($organization)->sent()->create(['provider_message_id' => 'SM-dup']);
    app(CurrentOrganization::class)->clear();

    $payload = ['MessageSid' => 'SM-dup', 'MessageStatus' => 'delivered'];
    postSignedTwilioWebhook($payload, $organization->id)->assertOk();
    postSignedTwilioWebhook($payload, $organization->id)->assertOk();

    app(CurrentOrganization::class)->set($organization);
    expect(WhatsappWebhookEvent::query()->where('provider', 'twilio')->count())->toBe(1);
});

it('ignore silencieusement un webhook pour un message inconnu', function (): void {
    $organization = Organization::factory()->create();

    postSignedTwilioWebhook(['MessageSid' => 'inconnu', 'MessageStatus' => 'delivered'], $organization->id)->assertOk();

    app(CurrentOrganization::class)->set($organization);
    expect(WhatsappMessage::query()->count())->toBe(0);
});
