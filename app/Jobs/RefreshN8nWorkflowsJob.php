<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\Organization\Models\Organization;
use App\Support\Integrations\N8nClient;
use App\Support\Integrations\N8nConnectionException;
use App\Support\Integrations\N8nWorkflowCache;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Récupère en arrière-plan les workflows de l'instance n8n d'une
 * organisation et les place dans N8nWorkflowCache, que la page Intégrations
 * se contente de lire.
 *
 * Unique par organisation : chaque visite de la page sur un cache vide
 * redemande la liste, et plusieurs onglets ouverts ne doivent pas empiler
 * autant d'appels vers l'instance du client.
 */
final class RefreshN8nWorkflowsJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 120;

    public int $uniqueFor = 120;

    public function __construct(
        public readonly int $organizationId,
    ) {}

    public function uniqueId(): string
    {
        return (string) $this->organizationId;
    }

    public function handle(N8nClient $n8n, N8nWorkflowCache $cache): void
    {
        // Organization est le tenant lui-même, jamais cloisonné : pas de
        // CurrentOrganization à positionner, contrairement aux autres jobs.
        $organization = Organization::query()->find($this->organizationId);

        if ($organization?->n8n_base_url === null || $organization->n8n_api_key === null) {
            $cache->forget($this->organizationId);

            return;
        }

        try {
            $cache->put($this->organizationId, $n8n->workflows($organization->n8n_base_url, $organization->n8n_api_key), null);
        } catch (N8nConnectionException $exception) {
            $cache->put($this->organizationId, [], $exception->getMessage());
        }
    }
}
