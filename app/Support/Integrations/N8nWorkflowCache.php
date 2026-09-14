<?php

declare(strict_types=1);

namespace App\Support\Integrations;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

/**
 * Liste des workflows n8n d'une organisation, alimentée uniquement par
 * RefreshN8nWorkflowsJob : la page Intégrations ne contacte jamais
 * l'instance n8n elle-même. Une instance lente ou arrêtée bloquait sinon
 * l'affichage jusqu'à 10 s par page de workflows (section 5 du CLAUDE.md :
 * tout traitement de plus de 2 s part en file d'attente).
 */
final class N8nWorkflowCache
{
    /**
     * Au-delà, la liste est redemandée en arrière-plan à la visite suivante.
     */
    private const TTL_MINUTES = 10;

    /**
     * @return array{workflows: list<array{id: string, name: string, active: bool, webhook_url: ?string}>, error: ?string, fetched_at: string}|null
     */
    public function get(int $organizationId): ?array
    {
        $entry = Cache::get($this->key($organizationId));

        return is_array($entry) ? $entry : null;
    }

    /**
     * @param  list<array{id: string, name: string, active: bool, webhook_url: ?string}>  $workflows
     */
    public function put(int $organizationId, array $workflows, ?string $error): void
    {
        Cache::put($this->key($organizationId), [
            'workflows' => $workflows,
            'error' => $error,
            'fetched_at' => CarbonImmutable::now()->toIso8601String(),
        ], CarbonImmutable::now()->addMinutes(self::TTL_MINUTES));
    }

    public function forget(int $organizationId): void
    {
        Cache::forget($this->key($organizationId));
    }

    private function key(int $organizationId): string
    {
        return "n8n-workflows:{$organizationId}";
    }
}
