<?php

declare(strict_types=1);

namespace App\Support\Integrations;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

/**
 * Appelle l'API publique d'une instance n8n appartenant au client
 * (https://docs.n8n.io/api/authentication/) : clé longue durée passée en
 * en-tête X-N8N-API-KEY, pas d'OAuth.
 *
 * Chaque instance est hébergée par le client, donc joignable ou non, à jour
 * ou non : toute erreur réseau ou HTTP est traduite en
 * N8nConnectionException plutôt que de remonter brute jusqu'à l'interface.
 */
final class N8nClient
{
    private const TIMEOUT_SECONDS = 10;

    /**
     * Taille de page maximale autorisée par l'API n8n (100 par défaut) :
     * moins d'allers-retours pour une instance qui a beaucoup de workflows.
     */
    private const PAGE_SIZE = 250;

    /**
     * Garde-fou contre une boucle infinie : certaines versions de n8n Cloud
     * (1.123 notamment) renvoient un nextCursor qui ne change jamais.
     * 40 pages × 250 = 10 000 workflows, bien au-delà d'un usage réel.
     */
    private const MAX_PAGES = 40;

    /**
     * Test de connexion : un seul appel, le plus léger possible (limit=1),
     * qui échoue vite et clairement si la clé ou l'URL sont mauvaises —
     * jamais le parcours de toutes les pages, inutile ici et lent sur une
     * grosse instance.
     */
    public function assertConnection(string $baseUrl, string $apiKey): void
    {
        $this->get($baseUrl, $apiKey, '/api/v1/workflows?'.http_build_query(['limit' => 1]));
    }

    /**
     * Tous les workflows de l'instance : l'API est paginée par curseur, lire
     * une seule page ferait disparaître sans message les workflows au-delà
     * des premiers.
     *
     * @return list<array{id: string, name: string, active: bool, webhook_url: ?string}>
     */
    public function workflows(string $baseUrl, string $apiKey): array
    {
        $rows = [];
        $cursor = null;
        $seenCursors = [];

        for ($page = 0; $page < self::MAX_PAGES; $page++) {
            $query = ['limit' => self::PAGE_SIZE];

            if ($cursor !== null) {
                $query['cursor'] = $cursor;
            }

            $payload = $this->get($baseUrl, $apiKey, '/api/v1/workflows?'.http_build_query($query));

            foreach (is_array($payload['data'] ?? null) ? $payload['data'] : [] as $row) {
                if (is_array($row)) {
                    $rows[] = $row;
                }
            }

            $next = $payload['nextCursor'] ?? null;

            // Fin de pagination, ou curseur déjà vu (bug du curseur figé) :
            // on s'arrête plutôt que de redemander la même page sans fin.
            if (! is_string($next) || $next === '' || in_array($next, $seenCursors, true)) {
                break;
            }

            $seenCursors[] = $next;
            $cursor = $next;
        }

        return array_map(fn (array $row): array => [
            'id' => (string) ($row['id'] ?? ''),
            'name' => (string) ($row['name'] ?? 'Sans nom'),
            'active' => (bool) ($row['active'] ?? false),
            'webhook_url' => $this->webhookUrl($baseUrl, $row),
        ], $rows);
    }

    /**
     * Reconstruit l'URL de production du nœud Webhook d'un workflow, pour
     * éviter à l'organisateur de la recopier à la main depuis n8n. Renvoie
     * null quand le workflow n'a pas de déclencheur webhook : il n'y a alors
     * rien où envoyer les événements Itaza.
     *
     * @param  array<string, mixed>  $workflow
     */
    private function webhookUrl(string $baseUrl, array $workflow): ?string
    {
        $nodes = is_array($workflow['nodes'] ?? null) ? $workflow['nodes'] : [];

        foreach ($nodes as $node) {
            if (! is_array($node) || ($node['type'] ?? null) !== 'n8n-nodes-base.webhook') {
                continue;
            }

            $path = $node['parameters']['path'] ?? $node['webhookId'] ?? null;

            if (is_string($path) && $path !== '') {
                return rtrim($baseUrl, '/').'/webhook/'.ltrim($path, '/');
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function get(string $baseUrl, string $apiKey, string $path): array
    {
        $url = rtrim($baseUrl, '/').$path;

        try {
            $response = Http::withHeaders(['X-N8N-API-KEY' => $apiKey])
                ->acceptJson()
                ->timeout(self::TIMEOUT_SECONDS)
                ->get($url);
        } catch (Throwable) {
            throw N8nConnectionException::unreachable($baseUrl);
        }

        if ($response->status() === 401 || $response->status() === 403) {
            throw N8nConnectionException::invalidKey();
        }

        if ($response->failed()) {
            throw N8nConnectionException::unexpectedStatus($response->status());
        }

        /** @var array<string, mixed> $decoded */
        $decoded = $response->json() ?? [];

        return $decoded;
    }

    /**
     * L'API n8n vit sous /api/v1 : on accepte une URL avec ou sans barre
     * finale, mais jamais un chemin déjà suffixé, qui produirait
     * /api/v1/api/v1/workflows.
     */
    public static function normalizeBaseUrl(string $baseUrl): string
    {
        $trimmed = rtrim(trim($baseUrl), '/');

        return Str::endsWith($trimmed, '/api/v1') ? Str::beforeLast($trimmed, '/api/v1') : $trimmed;
    }
}
