<?php

declare(strict_types=1);

use App\Domain\Organization\Models\MembershipRole;
use App\Support\MultiTenancy\CurrentOrganization;
use App\Support\Webhooks\Models\Webhook;

it('abonne Zapier à un événement et renvoie l\'identifiant de l\'abonnement', function (): void {
    ['organization' => $organization, 'doorStaff' => $owner] = makeCheckInEvent(MembershipRole::Owner);

    $response = $this->actingAs($owner, 'sanctum')->postJson('/api/v1/hooks', [
        'target_url' => 'https://hooks.zapier.com/hooks/catch/123/abcdef',
        'event' => 'registration.created',
    ]);

    $response->assertCreated();
    $response->assertJsonPath('data.target_url', 'https://hooks.zapier.com/hooks/catch/123/abcdef');
    $response->assertJsonPath('data.events', ['registration.created']);

    app(CurrentOrganization::class)->set($organization);
    expect(Webhook::query()->where('url', 'https://hooks.zapier.com/hooks/catch/123/abcdef')->exists())->toBeTrue();
    app(CurrentOrganization::class)->clear();
});

it('refuse une URL de destination non chiffrée', function (): void {
    ['doorStaff' => $owner] = makeCheckInEvent(MembershipRole::Owner);

    $response = $this->actingAs($owner, 'sanctum')->postJson('/api/v1/hooks', [
        'target_url' => 'http://hooks.zapier.com/hooks/catch/123/abcdef',
        'event' => 'registration.created',
    ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors('target_url');
});

it('refuse un événement inconnu à l\'abonnement', function (): void {
    ['doorStaff' => $owner] = makeCheckInEvent(MembershipRole::Owner);

    $response = $this->actingAs($owner, 'sanctum')->postJson('/api/v1/hooks', [
        'target_url' => 'https://hooks.zapier.com/hooks/catch/123/abcdef',
        'event' => 'paiement.recu',
    ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors('event');
});

it('désabonne un webhook existant', function (): void {
    ['organization' => $organization, 'doorStaff' => $owner] = makeCheckInEvent(MembershipRole::Owner);

    app(CurrentOrganization::class)->set($organization);
    $webhook = Webhook::factory()->for($organization)->create();
    app(CurrentOrganization::class)->clear();

    $response = $this->actingAs($owner, 'sanctum')->deleteJson("/api/v1/hooks/{$webhook->id}");

    $response->assertNoContent();

    app(CurrentOrganization::class)->set($organization);
    expect(Webhook::query()->find($webhook->id))->toBeNull();
    app(CurrentOrganization::class)->clear();
});

it('ne laisse pas désabonner le webhook d\'une autre organisation', function (): void {
    ['doorStaff' => $owner] = makeCheckInEvent(MembershipRole::Owner);
    ['organization' => $otherOrganization] = makeCheckInEvent(MembershipRole::Owner);

    app(CurrentOrganization::class)->set($otherOrganization);
    $foreignWebhook = Webhook::factory()->for($otherOrganization)->create();
    app(CurrentOrganization::class)->clear();

    $response = $this->actingAs($owner, 'sanctum')->deleteJson("/api/v1/hooks/{$foreignWebhook->id}");

    $response->assertNotFound();
});

it('renvoie une charge utile d\'exemple alignée sur les livraisons réelles', function (): void {
    ['doorStaff' => $owner] = makeCheckInEvent(MembershipRole::Owner);

    $response = $this->actingAs($owner, 'sanctum')->getJson('/api/v1/hooks/sample?event=registration.created');

    $response->assertOk();
    $response->assertJsonPath('0.event', 'registration.created');
    $response->assertJsonStructure([['event', 'delivery_id', 'data' => ['registration_id', 'event_id', 'status', 'email']]]);
});

it('refuse un abonnement à un rôle sans manageIntegrations', function (): void {
    ['doorStaff' => $editor] = makeCheckInEvent(MembershipRole::Editor);

    $response = $this->actingAs($editor, 'sanctum')->postJson('/api/v1/hooks', [
        'target_url' => 'https://hooks.zapier.com/hooks/catch/123/abcdef',
        'event' => 'registration.created',
    ]);

    $response->assertForbidden();
});

it('valide une clé API et renvoie le compte et l\'organisation (test de connexion Zapier/n8n)', function (): void {
    ['organization' => $organization, 'doorStaff' => $owner] = makeCheckInEvent(MembershipRole::Owner);

    $response = $this->actingAs($owner, 'sanctum')->getJson('/api/v1/me');

    $response->assertOk();
    $response->assertJsonPath('user.email', $owner->email);
    $response->assertJsonPath('organization.id', $organization->id);
    $response->assertJsonPath('organization.name', $organization->name);
});

it('refuse le test de connexion sans clé API valide', function (): void {
    $response = $this->getJson('/api/v1/me');

    $response->assertUnauthorized();
});

it('refuse un abonnement sans authentification', function (): void {
    $response = $this->postJson('/api/v1/hooks', [
        'target_url' => 'https://hooks.zapier.com/hooks/catch/123/abcdef',
        'event' => 'registration.created',
    ]);

    $response->assertUnauthorized();
});
