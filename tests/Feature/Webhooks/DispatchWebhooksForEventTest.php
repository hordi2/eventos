<?php

declare(strict_types=1);

use App\Domain\Organization\Models\Organization;
use App\Jobs\DeliverWebhookJob;
use App\Support\MultiTenancy\CurrentOrganization;
use App\Support\Webhooks\DispatchWebhooksForEvent;
use App\Support\Webhooks\Models\Webhook;
use App\Support\Webhooks\WebhookEvent;
use Illuminate\Support\Facades\Bus;

/**
 * DispatchWebhooksForEvent (comme ConfirmPromotedRegistration, déjà dans le
 * code) suppose que CurrentOrganization est déjà positionné par
 * l'appelant — les listeners de domaine s'exécutent toujours de façon
 * synchrone dans le contexte organisationnel déjà résolu par
 * resolve-guest-event/resolve-organization, jamais après un clear().
 */
it('déclenche une livraison pour chaque webhook actif souscrit à l\'événement', function (): void {
    Bus::fake();
    $organization = Organization::factory()->create();
    app(CurrentOrganization::class)->set($organization);
    $webhook = Webhook::factory()->for($organization)->create(['subscribed_events' => ['registration.created']]);

    app(DispatchWebhooksForEvent::class)->handle($organization->id, WebhookEvent::RegistrationCreated, ['registration_id' => 1]);
    app(CurrentOrganization::class)->clear();

    Bus::assertDispatched(DeliverWebhookJob::class, fn (DeliverWebhookJob $job): bool => $job->webhookId === $webhook->id
        && $job->organizationId === $organization->id
        && $job->eventName === 'registration.created');
});

it('ne déclenche rien pour un webhook non souscrit à cet événement', function (): void {
    Bus::fake();
    $organization = Organization::factory()->create();
    app(CurrentOrganization::class)->set($organization);
    Webhook::factory()->for($organization)->create(['subscribed_events' => ['registration.cancelled']]);

    app(DispatchWebhooksForEvent::class)->handle($organization->id, WebhookEvent::RegistrationCreated, []);
    app(CurrentOrganization::class)->clear();

    Bus::assertNotDispatched(DeliverWebhookJob::class);
});

it('ne déclenche rien pour un webhook désactivé', function (): void {
    Bus::fake();
    $organization = Organization::factory()->create();
    app(CurrentOrganization::class)->set($organization);
    Webhook::factory()->inactive()->for($organization)->create(['subscribed_events' => ['registration.created']]);

    app(DispatchWebhooksForEvent::class)->handle($organization->id, WebhookEvent::RegistrationCreated, []);
    app(CurrentOrganization::class)->clear();

    Bus::assertNotDispatched(DeliverWebhookJob::class);
});

it('ne déclenche rien pour le webhook d\'une autre organisation', function (): void {
    Bus::fake();
    $organization = Organization::factory()->create();
    $otherOrganization = Organization::factory()->create();
    app(CurrentOrganization::class)->set($otherOrganization);
    Webhook::factory()->for($otherOrganization)->create(['subscribed_events' => ['registration.created']]);
    app(CurrentOrganization::class)->clear();

    app(CurrentOrganization::class)->set($organization);
    app(DispatchWebhooksForEvent::class)->handle($organization->id, WebhookEvent::RegistrationCreated, []);
    app(CurrentOrganization::class)->clear();

    Bus::assertNotDispatched(DeliverWebhookJob::class);
});
