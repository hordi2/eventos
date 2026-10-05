<?php

declare(strict_types=1);

use App\Domain\Event\Models\Event;
use App\Domain\Form\Models\Form;
use App\Domain\Form\Models\FormVersion;
use App\Domain\Form\Models\Registration;
use App\Domain\Form\Models\RegistrationStatus;
use App\Domain\Organization\Models\MembershipRole;
use App\Domain\Organization\Models\Organization;
use App\Support\MultiTenancy\CurrentOrganization;

/**
 * Serveur MCP (D4) : l'organisateur branche l'assistant IA de son choix sur
 * son compte. JSON-RPC 2.0, lecture seule.
 */
function mcpCall(array $payload): array
{
    return $payload + ['jsonrpc' => '2.0'];
}

it('se présente à l\'assistant qui se connecte', function (): void {
    ['doorStaff' => $owner] = makeCheckInEvent(MembershipRole::Owner);

    $this->actingAs($owner, 'sanctum')
        ->postJson('/api/v1/mcp', mcpCall(['id' => 1, 'method' => 'initialize']))
        ->assertOk()
        ->assertJsonPath('result.serverInfo.name', 'itaza-invitation')
        ->assertJsonPath('result.capabilities.tools.listChanged', false);
});

it('annonce ses outils', function (): void {
    ['doorStaff' => $owner] = makeCheckInEvent(MembershipRole::Owner);

    $response = $this->actingAs($owner, 'sanctum')
        ->postJson('/api/v1/mcp', mcpCall(['id' => 2, 'method' => 'tools/list']))
        ->assertOk();

    expect(array_column($response->json('result.tools'), 'name'))
        ->toBe(['lister_evenements', 'detail_evenement', 'rechercher_inscrit']);
});

it('répond sur les événements de la seule organisation du jeton', function (): void {
    ['organization' => $organization, 'event' => $event, 'doorStaff' => $owner] = makeCheckInEvent(MembershipRole::Owner);

    // Un événement d'une autre organisation, que l'assistant ne doit pas voir.
    $stranger = Organization::factory()->create();
    app(CurrentOrganization::class)->set($stranger);
    Event::factory()->for($stranger)->published()->create(['title' => 'Gala d’un inconnu']);
    app(CurrentOrganization::class)->clear();

    $response = $this->actingAs($owner, 'sanctum')
        ->postJson('/api/v1/mcp', mcpCall([
            'id' => 3,
            'method' => 'tools/call',
            'params' => ['name' => 'lister_evenements', 'arguments' => []],
        ]))
        ->assertOk();

    $text = $response->json('result.content.0.text');

    expect($text)->toContain($event->title)
        ->and($text)->not->toContain('Gala d’un inconnu');
});

it('retrouve un inscrit par son nom', function (): void {
    ['organization' => $organization, 'event' => $event, 'doorStaff' => $owner] = makeCheckInEvent(MembershipRole::Owner);

    app(CurrentOrganization::class)->set($organization);
    $form = Form::factory()->create([
        'organization_id' => $organization->id,
        'event_id' => $event->id,
        'created_by' => $owner->id,
    ]);
    $version = FormVersion::factory()->create(['organization_id' => $organization->id, 'form_id' => $form->id]);
    Registration::factory()->create([
        'organization_id' => $organization->id,
        'event_id' => $event->id,
        'form_version_id' => $version->id,
        'status' => RegistrationStatus::Confirmed,
        'first_name' => 'Awa',
        'last_name' => 'Diallo',
        'email' => 'awa@example.com',
    ]);
    app(CurrentOrganization::class)->clear();

    $response = $this->actingAs($owner, 'sanctum')
        ->postJson('/api/v1/mcp', mcpCall([
            'id' => 4,
            'method' => 'tools/call',
            'params' => ['name' => 'rechercher_inscrit', 'arguments' => ['evenement_id' => $event->id, 'recherche' => 'diallo']],
        ]))
        ->assertOk();

    expect($response->json('result.content.0.text'))->toContain('Awa Diallo');
});

it('refuse un outil inconnu, et ne répond pas à une notification', function (): void {
    ['doorStaff' => $owner] = makeCheckInEvent(MembershipRole::Owner);

    $this->actingAs($owner, 'sanctum')
        ->postJson('/api/v1/mcp', mcpCall([
            'id' => 5,
            'method' => 'tools/call',
            'params' => ['name' => 'supprimer_tout', 'arguments' => []],
        ]))
        ->assertOk()
        ->assertJsonPath('error.code', -32602);

    // Une notification n'a pas d'identifiant : rien ne lui est renvoyé.
    $this->actingAs($owner, 'sanctum')
        ->postJson('/api/v1/mcp', mcpCall(['method' => 'notifications/initialized']))
        ->assertNoContent();
});

it('ferme la porte à qui n\'a pas de jeton', function (): void {
    $this->postJson('/api/v1/mcp', mcpCall(['id' => 6, 'method' => 'tools/list']))
        ->assertUnauthorized();
});
