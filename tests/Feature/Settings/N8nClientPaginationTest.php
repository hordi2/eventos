<?php

declare(strict_types=1);

use App\Support\Integrations\N8nClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/**
 * @return array<string, mixed>
 */
function n8nPaginatedWorkflow(string $id): array
{
    return ['id' => $id, 'name' => "Workflow {$id}", 'active' => true, 'nodes' => []];
}

/**
 * @return array<int|string, mixed>
 */
function n8nQuery(Request $request): array
{
    parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

    return $query;
}

it('parcourt toutes les pages de workflows n8n en suivant nextCursor', function (): void {
    Http::fake(fn (Request $request) => match (n8nQuery($request)['cursor'] ?? null) {
        null => Http::response(['data' => [n8nPaginatedWorkflow('a')], 'nextCursor' => 'page-2']),
        'page-2' => Http::response(['data' => [n8nPaginatedWorkflow('b')], 'nextCursor' => 'page-3']),
        'page-3' => Http::response(['data' => [n8nPaginatedWorkflow('c')], 'nextCursor' => null]),
    });

    $workflows = app(N8nClient::class)->workflows('https://n8n.test', 'cle');

    expect(array_column($workflows, 'id'))->toBe(['a', 'b', 'c']);
    Http::assertSentCount(3);
    Http::assertSent(fn (Request $request): bool => (n8nQuery($request)['limit'] ?? null) === '250');
});

it('s\'arrête quand l\'instance renvoie toujours le même curseur, sans boucler indéfiniment', function (): void {
    // Bug constaté sur n8n Cloud 1.123 : nextCursor ne change jamais.
    Http::fake(fn () => Http::response(['data' => [n8nPaginatedWorkflow('x')], 'nextCursor' => 'curseur-fige']));

    $workflows = app(N8nClient::class)->workflows('https://n8n.test', 'cle');

    expect($workflows)->toHaveCount(2);
    Http::assertSentCount(2);
});

it('valide la connexion en une seule requête, sans parcourir toutes les pages', function (): void {
    Http::fake(fn () => Http::response(['data' => [n8nPaginatedWorkflow('a')], 'nextCursor' => 'page-2']));

    app(N8nClient::class)->assertConnection('https://n8n.test', 'cle');

    Http::assertSentCount(1);
    Http::assertSent(fn (Request $request): bool => (n8nQuery($request)['limit'] ?? null) === '1');
});
