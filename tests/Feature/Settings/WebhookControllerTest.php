<?php

declare(strict_types=1);

use App\Domain\Organization\Models\MembershipRole;
use App\Support\MultiTenancy\CurrentOrganization;
use App\Support\Webhooks\Models\Webhook;

it('crée un webhook et affiche le secret en clair une seule fois via le flash', function (): void {
    ['organization' => $organization, 'doorStaff' => $owner] = makeCheckInEvent(MembershipRole::Owner);

    $response = $this->actingAs($owner)->post('/settings/api/webhooks', [
        'url' => 'https://example.com/hooks/itaza',
        'subscribed_events' => ['registration.created'],
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('plainSecret');

    app(CurrentOrganization::class)->set($organization);
    $webhook = Webhook::query()->where('url', 'https://example.com/hooks/itaza')->firstOrFail();
    expect($webhook->subscribed_events)->toBe(['registration.created']);
    expect($webhook->is_active)->toBeTrue();
    app(CurrentOrganization::class)->clear();
});

it('refuse un événement inconnu', function (): void {
    ['doorStaff' => $owner] = makeCheckInEvent(MembershipRole::Owner);

    $response = $this->actingAs($owner)->post('/settings/api/webhooks', [
        'url' => 'https://example.com/hooks/itaza',
        'subscribed_events' => ['un.evenement.inconnu'],
    ]);

    $response->assertSessionHasErrors('subscribed_events.0');
});

it('désactive un webhook existant', function (): void {
    ['organization' => $organization, 'doorStaff' => $owner] = makeCheckInEvent(MembershipRole::Owner);
    app(CurrentOrganization::class)->set($organization);
    $webhook = Webhook::factory()->for($organization)->create();
    app(CurrentOrganization::class)->clear();

    $this->actingAs($owner)->patch("/settings/api/webhooks/{$webhook->id}", [
        'url' => $webhook->url,
        'subscribed_events' => $webhook->subscribed_events,
        'is_active' => false,
    ]);

    expect($webhook->fresh()->is_active)->toBeFalse();
});

it('supprime un webhook', function (): void {
    ['organization' => $organization, 'doorStaff' => $owner] = makeCheckInEvent(MembershipRole::Owner);
    app(CurrentOrganization::class)->set($organization);
    $webhook = Webhook::factory()->for($organization)->create();
    app(CurrentOrganization::class)->clear();

    $this->actingAs($owner)->delete("/settings/api/webhooks/{$webhook->id}");

    app(CurrentOrganization::class)->set($organization);
    expect(Webhook::query()->find($webhook->id))->toBeNull();
    app(CurrentOrganization::class)->clear();
});

it('refuse la gestion des webhooks à un rôle sans capacité manageIntegrations', function (): void {
    ['doorStaff' => $editor] = makeCheckInEvent(MembershipRole::Editor);

    $this->actingAs($editor)->post('/settings/api/webhooks', [
        'url' => 'https://example.com/hooks/itaza',
        'subscribed_events' => ['registration.created'],
    ])->assertForbidden();
});
