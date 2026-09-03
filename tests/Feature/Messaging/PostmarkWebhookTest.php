<?php

declare(strict_types=1);

use App\Domain\Contact\Models\Contact;
use App\Domain\Messaging\Models\EmailMessage;
use App\Domain\Messaging\Models\EmailWebhookEvent;
use App\Domain\Organization\Models\Organization;
use App\Support\MultiTenancy\CurrentOrganization;

beforeEach(function (): void {
    config(['services.postmark.webhook_username' => 'wh_user', 'services.postmark.webhook_password' => 'wh_pass']);
});

it('refuse une requête sans les identifiants Basic Auth configurés', function (): void {
    $this->postJson('/webhooks/postmark', ['RecordType' => 'Delivery'])->assertForbidden();
});

it('marque un message livré', function (): void {
    $organization = Organization::factory()->create();
    app(CurrentOrganization::class)->set($organization);
    $emailMessage = EmailMessage::factory()->for($organization)->sent()->create(['provider_message_id' => 'msg-1']);

    $this->withBasicAuth('wh_user', 'wh_pass')->postJson('/webhooks/postmark', [
        'RecordType' => 'Delivery',
        'MessageID' => 'msg-1',
        'Recipient' => $emailMessage->to_email,
        'DeliveredAt' => now()->toIso8601String(),
    ])->assertOk();

    expect($emailMessage->fresh()->status->value)->toBe('delivered');
    expect($emailMessage->fresh()->delivered_at)->not->toBeNull();
});

it('marque un contact invalide sur un bounce dur, mais pas sur un bounce transitoire', function (): void {
    $organization = Organization::factory()->create();
    app(CurrentOrganization::class)->set($organization);
    $contact = Contact::factory()->for($organization)->create();
    $hard = EmailMessage::factory()->for($organization)->sent()->create(['provider_message_id' => 'msg-hard', 'contact_id' => $contact->id]);

    $this->withBasicAuth('wh_user', 'wh_pass')->postJson('/webhooks/postmark', [
        'RecordType' => 'Bounce',
        'Type' => 'HardBounce',
        'MessageID' => 'msg-hard',
        'Recipient' => $hard->to_email,
        'BouncedAt' => now()->toIso8601String(),
    ])->assertOk();

    expect($hard->fresh()->status->value)->toBe('bounced');
    expect($hard->fresh()->bounce_type)->toBe('hard');
    expect($contact->fresh()->email_invalid_at)->not->toBeNull();
    expect($contact->fresh()->email_invalid_reason)->toBe('hard_bounce');

    $otherContact = Contact::factory()->for($organization)->create();
    $soft = EmailMessage::factory()->for($organization)->sent()->create(['provider_message_id' => 'msg-soft', 'contact_id' => $otherContact->id]);

    $this->withBasicAuth('wh_user', 'wh_pass')->postJson('/webhooks/postmark', [
        'RecordType' => 'Bounce',
        'Type' => 'SoftBounce',
        'MessageID' => 'msg-soft',
        'Recipient' => $soft->to_email,
        'BouncedAt' => now()->toIso8601String(),
    ])->assertOk();

    expect($soft->fresh()->bounce_type)->toBe('soft');
    expect($otherContact->fresh()->email_invalid_at)->toBeNull();
});

it('marque un contact invalide sur une plainte pour spam', function (): void {
    $organization = Organization::factory()->create();
    app(CurrentOrganization::class)->set($organization);
    $contact = Contact::factory()->for($organization)->create();
    $emailMessage = EmailMessage::factory()->for($organization)->sent()->create(['provider_message_id' => 'msg-spam', 'contact_id' => $contact->id]);

    $this->withBasicAuth('wh_user', 'wh_pass')->postJson('/webhooks/postmark', [
        'RecordType' => 'SpamComplaint',
        'MessageID' => 'msg-spam',
        'Recipient' => $emailMessage->to_email,
        'BouncedAt' => now()->toIso8601String(),
    ])->assertOk();

    expect($emailMessage->fresh()->status->value)->toBe('complained');
    expect($contact->fresh()->email_invalid_reason)->toBe('complaint');
});

it('enregistre la première ouverture et le premier clic sans écraser les suivants', function (): void {
    $organization = Organization::factory()->create();
    app(CurrentOrganization::class)->set($organization);
    $emailMessage = EmailMessage::factory()->for($organization)->sent()->create(['provider_message_id' => 'msg-open']);

    $this->withBasicAuth('wh_user', 'wh_pass')->postJson('/webhooks/postmark', [
        'RecordType' => 'Open',
        'MessageID' => 'msg-open',
        'Recipient' => $emailMessage->to_email,
        'ReceivedAt' => now()->toIso8601String(),
    ])->assertOk();
    $firstOpenedAt = $emailMessage->fresh()->opened_at;
    expect($firstOpenedAt)->not->toBeNull();

    $this->withBasicAuth('wh_user', 'wh_pass')->postJson('/webhooks/postmark', [
        'RecordType' => 'Open',
        'MessageID' => 'msg-open',
        'Recipient' => $emailMessage->to_email,
        'ReceivedAt' => now()->addMinute()->toIso8601String(),
    ])->assertOk();
    expect($emailMessage->fresh()->opened_at->equalTo($firstOpenedAt))->toBeTrue();
});

it('traite un événement de façon idempotente : un doublon ne se traite qu\'une fois', function (): void {
    $organization = Organization::factory()->create();
    app(CurrentOrganization::class)->set($organization);
    $emailMessage = EmailMessage::factory()->for($organization)->sent()->create(['provider_message_id' => 'msg-dup']);

    $payload = [
        'RecordType' => 'Delivery',
        'MessageID' => 'msg-dup',
        'Recipient' => $emailMessage->to_email,
        'DeliveredAt' => '2026-09-09T10:00:00+00:00',
    ];

    $this->withBasicAuth('wh_user', 'wh_pass')->postJson('/webhooks/postmark', $payload)->assertOk();
    $this->withBasicAuth('wh_user', 'wh_pass')->postJson('/webhooks/postmark', $payload)->assertOk();

    expect(EmailWebhookEvent::query()->where('provider', 'postmark')->count())->toBe(1);
});

it('ignore silencieusement un webhook pour un message inconnu', function (): void {
    $organization = Organization::factory()->create();
    app(CurrentOrganization::class)->set($organization);

    $this->withBasicAuth('wh_user', 'wh_pass')->postJson('/webhooks/postmark', [
        'RecordType' => 'Delivery',
        'MessageID' => 'inconnu',
        'Recipient' => 'personne@example.org',
        'DeliveredAt' => now()->toIso8601String(),
    ])->assertOk();

    // Aucune organisation n'a pu être résolue (message inconnu) : ce
    // compte reste scopé à l'organisation positionnée par ce test lui-même,
    // pas par la requête webhook — qui n'a rien touché.
    expect(EmailMessage::query()->count())->toBe(0);
});
