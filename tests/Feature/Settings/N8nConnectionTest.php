<?php

declare(strict_types=1);

use App\Domain\Organization\Models\MembershipRole;
use App\Support\MultiTenancy\CurrentOrganization;
use App\Support\Webhooks\Models\Webhook;
use Illuminate\Support\Facades\Http;

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

it('connecte n8n après avoir vérifié la clé auprès de l\'instance', function (): void {
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
});

it('stocke la clé n8n chiffrée, jamais en clair en base', function (): void {
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
    Http::fake(['https://n8n.test/api/v1/workflows*' => Http::response(['message' => 'unauthorized'], 401)]);
    ['organization' => $organization, 'doorStaff' => $admin] = makeCheckInEvent(MembershipRole::Admin);

    $response = $this->actingAs($admin)->post('/settings/api/n8n', [
        'base_url' => 'https://n8n.test',
        'api_key' => 'cle-invalide',
    ]);

    $response->assertSessionHasErrors('api_key');
    expect($organization->fresh()->n8n_api_key)->toBeNull();
});

it('refuse une instance n8n en http non chiffré', function (): void {
    ['doorStaff' => $admin] = makeCheckInEvent(MembershipRole::Admin);

    $response = $this->actingAs($admin)->post('/settings/api/n8n', [
        'base_url' => 'http://n8n.test',
        'api_key' => 'cle-n8n',
    ]);

    $response->assertSessionHasErrors('base_url');
});

it('liste les workflows n8n et reconstruit l\'URL du nœud webhook', function (): void {
    Http::fake(['https://n8n.test/api/v1/workflows*' => Http::response(n8nWorkflowsResponse())]);
    ['organization' => $organization, 'doorStaff' => $admin] = makeCheckInEvent(MembershipRole::Admin);

    app(CurrentOrganization::class)->set($organization);
    $organization->update(['n8n_base_url' => 'https://n8n.test', 'n8n_api_key' => 'cle', 'n8n_connected_at' => now()]);
    app(CurrentOrganization::class)->clear();

    $response = $this->actingAs($admin)->get('/settings/api');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('n8n.connected', true)
        ->has('n8n.workflows', 2)
        ->where('n8n.workflows.0.webhook_url', 'https://n8n.test/webhook/itaza-inscriptions')
        ->where('n8n.workflows.1.webhook_url', null));
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

it('affiche l\'erreur sans casser la page quand l\'instance n8n ne répond plus', function (): void {
    Http::fake(['https://n8n.test/api/v1/workflows*' => Http::response('', 500)]);
    ['organization' => $organization, 'doorStaff' => $admin] = makeCheckInEvent(MembershipRole::Admin);

    app(CurrentOrganization::class)->set($organization);
    $organization->update(['n8n_base_url' => 'https://n8n.test', 'n8n_api_key' => 'cle', 'n8n_connected_at' => now()]);
    app(CurrentOrganization::class)->clear();

    $response = $this->actingAs($admin)->get('/settings/api');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('n8n.connected', true)
        ->has('n8n.error'));
});

it('déconnecte n8n', function (): void {
    ['organization' => $organization, 'doorStaff' => $admin] = makeCheckInEvent(MembershipRole::Admin);

    app(CurrentOrganization::class)->set($organization);
    $organization->update(['n8n_base_url' => 'https://n8n.test', 'n8n_api_key' => 'cle', 'n8n_connected_at' => now()]);
    app(CurrentOrganization::class)->clear();

    $this->actingAs($admin)->delete('/settings/api/n8n')->assertRedirect();

    expect($organization->fresh()->n8n_api_key)->toBeNull();
});

it('refuse la connexion n8n à un rôle sans manageIntegrations', function (): void {
    ['doorStaff' => $editor] = makeCheckInEvent(MembershipRole::Editor);

    $response = $this->actingAs($editor)->post('/settings/api/n8n', [
        'base_url' => 'https://n8n.test',
        'api_key' => 'cle-n8n',
    ]);

    $response->assertForbidden();
});
