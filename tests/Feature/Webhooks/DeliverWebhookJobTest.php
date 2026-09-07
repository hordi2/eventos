<?php

declare(strict_types=1);

use App\Domain\Organization\Models\Organization;
use App\Jobs\DeliverWebhookJob;
use App\Support\MultiTenancy\CurrentOrganization;
use App\Support\Webhooks\Models\Webhook;
use Illuminate\Support\Facades\Http;

it('livre l\'événement signé et journalise le succès', function (): void {
    Http::fake(['https://example.com/hooks/itaza' => Http::response(['ok' => true], 200)]);
    $organization = Organization::factory()->create();
    app(CurrentOrganization::class)->set($organization);
    $webhook = Webhook::factory()->for($organization)->create([
        'url' => 'https://example.com/hooks/itaza',
        'secret' => 'un-secret-de-test',
    ]);
    app(CurrentOrganization::class)->clear();

    (new DeliverWebhookJob($organization->id, $webhook->id, 'registration.created', ['registration_id' => 42]))->handle(
        app(CurrentOrganization::class),
    );

    Http::assertSent(function ($request) {
        $body = json_decode((string) $request->body(), true);
        $expectedSignature = hash_hmac('sha256', (string) $request->body(), 'un-secret-de-test');

        return $request->url() === 'https://example.com/hooks/itaza'
            && $request->hasHeader('X-Itaza-Signature', "sha256={$expectedSignature}")
            && $request->hasHeader('X-Itaza-Event', 'registration.created')
            && $body['event'] === 'registration.created'
            && $body['data']['registration_id'] === 42;
    });

    app(CurrentOrganization::class)->set($organization);
    $fresh = $webhook->fresh();
    expect($fresh->last_delivery_status)->toBe('success');
    expect($fresh->last_delivery_at)->not->toBeNull();
    app(CurrentOrganization::class)->clear();
});

it('journalise l\'échec et relance l\'exception pour déclencher un réessai', function (): void {
    Http::fake(['https://example.com/hooks/itaza' => Http::response('erreur', 500)]);
    $organization = Organization::factory()->create();
    app(CurrentOrganization::class)->set($organization);
    $webhook = Webhook::factory()->for($organization)->create(['url' => 'https://example.com/hooks/itaza']);
    app(CurrentOrganization::class)->clear();

    $job = new DeliverWebhookJob($organization->id, $webhook->id, 'registration.created', []);

    expect(fn () => $job->handle(app(CurrentOrganization::class)))->toThrow(Exception::class);

    app(CurrentOrganization::class)->set($organization);
    expect($webhook->fresh()->last_delivery_status)->toBe('failed');
    app(CurrentOrganization::class)->clear();
});

it('ne fait aucun appel HTTP pour un webhook désactivé entre-temps', function (): void {
    Http::fake();
    $organization = Organization::factory()->create();
    app(CurrentOrganization::class)->set($organization);
    $webhook = Webhook::factory()->inactive()->for($organization)->create();
    app(CurrentOrganization::class)->clear();

    (new DeliverWebhookJob($organization->id, $webhook->id, 'registration.created', []))->handle(app(CurrentOrganization::class));

    Http::assertNothingSent();
});
