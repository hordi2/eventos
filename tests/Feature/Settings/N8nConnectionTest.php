<?php

declare(strict_types=1);

use App\Domain\Organization\Models\MembershipRole;
use App\Domain\Organization\Models\Organization;
use App\Jobs\RefreshN8nWorkflowsJob;
use App\Support\Integrations\N8nWorkflowCache;
use App\Support\MultiTenancy\CurrentOrganization;
use App\Support\Webhooks\Models\Webhook;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

/**
 * Réponse type de GET /api/v1/workflows d'une instance n8n : un workflow
 * avec un nœud Webhook, un autre sans (rien où envoyer les événements).
 *
 * @return array<string, mixed>
 */
function n8nWorkflowsResponse(): array
{
    return [
        'data' => [
            [
                'id' => 'wf-1',
                'name' => 'Inscriptions vers Google Sheets',
                'active' => true,
                'nodes' => [
                    ['type' => 'n8n-nodes-base.webhook', 'parameters' => ['path' => 'itaza-inscriptions']],
                ],
            ],
            [
                'id' => 'wf-2',
                'name' => 'Rapport hebdomadaire',
                'active' => false,
                'nodes' => [
                    ['type' => 'n8n-nodes-base.scheduleTrigger', 'parameters' => []],
                ],
            ],
        ],
    ];
}

function markN8nConnected(Organization $organization): void
{
    app(CurrentOrganization::class)->set($organization);
    $organization->update(['n8n_base_url' => 'https://n8n.test', 'n8n_api_key' => 'cle', 'n8n_connected_at' => now()]);
    app(CurrentOrganization::class)->clear();
}

/**
 * Filtré sur l'instance n8n plutôt que Http::assertNothingSent() : le rendu
 * serveur d'Inertia passe lui aussi par le client HTTP de Laravel.
 */
function assertN8nNeverCalled(): void
{
    Http::assertNotSent(fn (Request $request): bool => str_starts_with($request->url(), 'https://n8n.test'));
}

it('connecte n8n après avoir vérifié la clé, puis planifie la récupération des workflows', function (): void {
    Queue::fake();
    Http::fake(['https://n8n.test/api/v1/workflows*' => Http::response(n8nWorkflowsResponse())]);
    ['organization' => $organization, 'doorStaff' => $admin] = makeCheckInEvent(MembershipRole::Admin);

    $response = $this->actingAs($admin)->post('/settings/api/n8n', [
        'base_url' => 'https://n8n.test',
        'api_key' => 'cle-n8n-valide',
    ]);

    $response->assertRedirect();
    $fresh = $organization->fresh();
    expect($fresh->n8n_base_url)->toBe('https://n8n.test');
    expect($fresh->n8n_api_key)->toBe('cle-n8n-valide');
    expect($fresh->n8n_connected_at)->not->toBeNull();
    Queue::assertPushed(RefreshN8nWorkflowsJob::class, fn (RefreshN8nWorkflowsJob $job): bool => $job->organizationId === $organization->id);
});

it('stocke la clé n8n chiffrée, jamais en clair en base', function (): void {
    Queue::fake();
    Http::fake(['https://n8n.test/api/v1/workflows*' => Http::response(n8nWorkflowsResponse())]);
    ['organization' => $organization, 'doorStaff' => $admin] = makeCheckInEvent(MembershipRole::Admin);

    $this->actingAs($admin)->post('/settings/api/n8n', [
        'base_url' => 'https://n8n.test',
        'api_key' => 'cle-n8n-secrete',
    ]);

    $stored = DB::table('organizations')->where('id', $organization->id)->value('n8n_api_key');
    expect($stored)->not->toBe('cle-n8n-secrete');
    expect($stored)->not->toContain('cle-n8n-secrete');
});

it('refuse d\'enregistrer une clé n8n rejetée par l\'instance', function (): void {
    Queue::fake();
    Http::fake(['https://n8n.test/api/v1/workflows*' => Http::response(['message' => 'unauthorized'], 401)]);
    ['organization' => $organization, 'doorStaff' => $admin] = makeCheckInEvent(MembershipRole::Admin);

    $response = $this->actingAs($admin)->post('/settings/api/n8n', [
        'base_url' => 'https://n8n.test',
        'api_key' => 'cle-invalide',
    ]);

    $response->assertSessionHasErrors('api_key');
    expect($organization->fresh()->n8n_api_key)->toBeNull();
    Queue::assertNothingPushed();
});

it('refuse une instance n8n en http non chiffré', function (): void {
    ['doorStaff' => $admin] = makeCheckInEvent(MembershipRole::Admin);

    $response = $this->actingAs($admin)->post('/settings/api/n8n', [
        'base_url' => 'http://n8n.test',
        'api_key' => 'cle-n8n',
    ]);

    $response->assertSessionHasErrors('base_url');
});

it('n\'appelle jamais n8n à l\'affichage de la page et planifie la récupération quand la liste n\'est pas en cache', function (): void {
    Queue::fake();
    Http::fake();
    ['organization' => $organization, 'doorStaff' => $admin] = makeCheckInEvent(MembershipRole::Admin);
    markN8nConnected($organization);

    $response = $this->actingAs($admin)->get('/settings/api');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('n8n.connected', true)
        ->where('n8n.loading', true)
        ->has('n8n.workflows', 0));
    assertN8nNeverCalled();
    Queue::assertPushed(RefreshN8nWorkflowsJob::class);
});

it('affiche les workflows depuis le cache, sans contacter n8n', function (): void {
    Queue::fake();
    Http::fake();
    ['organization' => $organization, 'doorStaff' => $admin] = makeCheckInEvent(MembershipRole::Admin);
    markN8nConnected($organization);

    app(N8nWorkflowCache::class)->put($organization->id, [
        ['id' => 'wf-1', 'name' => 'Inscriptions vers Google Sheets', 'active' => true, 'webhook_url' => 'https://n8n.test/webhook/itaza-inscriptions'],
    ], null);

    $response = $this->actingAs($admin)->get('/settings/api');

    $response->assertInertia(fn ($page) => $page
        ->where('n8n.loading', false)
        ->has('n8n.workflows', 1)
        ->where('n8n.workflows.0.webhook_url', 'https://n8n.test/webhook/itaza-inscriptions'));
    assertN8nNeverCalled();
    Queue::assertNothingPushed();
});

it('récupère les workflows en arrière-plan et reconstruit l\'URL du nœud webhook', function (): void {
    Http::fake(['https://n8n.test/api/v1/workflows*' => Http::response(n8nWorkflowsResponse())]);
    ['organization' => $organization] = makeCheckInEvent(MembershipRole::Admin);
    markN8nConnected($organization);

    RefreshN8nWorkflowsJob::dispatchSync($organization->id);

    $cached = app(N8nWorkflowCache::class)->get($organization->id);
    expect($cached)->not->toBeNull();
    expect($cached['error'])->toBeNull();
    expect($cached['workflows'])->toHaveCount(2);
    expect($cached['workflows'][0]['webhook_url'])->toBe('https://n8n.test/webhook/itaza-inscriptions');
    expect($cached['workflows'][1]['webhook_url'])->toBeNull();
});

it('mémorise l\'erreur quand l\'instance n8n ne répond plus, et la page l\'affiche sans planter', function (): void {
    Http::fake(['https://n8n.test/api/v1/workflows*' => Http::response('', 500)]);
    ['organization' => $organization, 'doorStaff' => $admin] = makeCheckInEvent(MembershipRole::Admin);
    markN8nConnected($organization);

    RefreshN8nWorkflowsJob::dispatchSync($organization->id);

    $response = $this->actingAs($admin)->get('/settings/api');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('n8n.loading', false)
        ->where('n8n.error', fn ($error): bool => is_string($error) && $error !== ''));
});

it('rafraîchir vide la liste en cache et relance la récupération', function (): void {
    Queue::fake();
    ['organization' => $organization, 'doorStaff' => $admin] = makeCheckInEvent(MembershipRole::Admin);
    markN8nConnected($organization);
    app(N8nWorkflowCache::class)->put($organization->id, [], null);

    $this->actingAs($admin)->post('/settings/api/n8n/refresh')->assertRedirect();

    expect(app(N8nWorkflowCache::class)->get($organization->id))->toBeNull();
    Queue::assertPushed(RefreshN8nWorkflowsJob::class);
});

it('branche un workflow n8n en créant le webhook sortant correspondant', function (): void {
    ['organization' => $organization, 'doorStaff' => $admin] = makeCheckInEvent(MembershipRole::Admin);

    $response = $this->actingAs($admin)->post('/settings/api/n8n/workflows', [
        'webhook_url' => 'https://n8n.test/webhook/itaza-inscriptions',
        'events' => ['registration.created'],
    ]);

    $response->assertRedirect();

    app(CurrentOrganization::class)->set($organization);
    $webhook = Webhook::query()->where('url', 'https://n8n.test/webhook/itaza-inscriptions')->firstOrFail();
    expect($webhook->subscribed_events)->toBe(['registration.created']);
    app(CurrentOrganization::class)->clear();
});

it('déconnecte n8n et oublie la liste en cache', function (): void {
    ['organization' => $organization, 'doorStaff' => $admin] = makeCheckInEvent(MembershipRole::Admin);
    markN8nConnected($organization);
    app(N8nWorkflowCache::class)->put($organization->id, [], null);

    $this->actingAs($admin)->delete('/settings/api/n8n')->assertRedirect();

    expect($organization->fresh()->n8n_api_key)->toBeNull();
    expect(app(N8nWorkflowCache::class)->get($organization->id))->toBeNull();
});

it('refuse la connexion n8n à un rôle sans manageIntegrations', function (): void {
    ['doorStaff' => $editor] = makeCheckInEvent(MembershipRole::Editor);

    $response = $this->actingAs($editor)->post('/settings/api/n8n', [
        'base_url' => 'https://n8n.test',
        'api_key' => 'cle-n8n',
    ]);

    $response->assertForbidden();
});
