<?php

declare(strict_types=1);

namespace App\Http\Controllers\Organizer\Settings;

use App\Domain\Organization\Models\Organization;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organizer\Settings\ConnectN8nRequest;
use App\Http\Requests\Organizer\Settings\ConnectN8nWorkflowRequest;
use App\Jobs\RefreshN8nWorkflowsJob;
use App\Models\User;
use App\Support\Integrations\N8nClient;
use App\Support\Integrations\N8nConnectionException;
use App\Support\Integrations\N8nWorkflowCache;
use App\Support\MultiTenancy\CurrentOrganization;
use App\Support\Webhooks\Actions\CreateWebhook;
use Illuminate\Http\RedirectResponse;

final class N8nConnectionController extends Controller
{
    public function __construct(
        private readonly N8nClient $n8n,
        private readonly N8nWorkflowCache $workflowCache,
    ) {}

    public function store(ConnectN8nRequest $request): RedirectResponse
    {
        $baseUrl = N8nClient::normalizeBaseUrl($request->string('base_url')->toString());
        $apiKey = $request->string('api_key')->toString();

        // La clé n'est enregistrée qu'une fois prouvée valide : sans cela
        // l'organisateur repartirait avec une connexion affichée comme
        // établie mais muette au premier événement. Un seul appel léger
        // (limit=1), jamais la liste complète, qui part en file d'attente.
        try {
            $this->n8n->assertConnection($baseUrl, $apiKey);
        } catch (N8nConnectionException $exception) {
            return back()->withErrors(['api_key' => $exception->getMessage()]);
        }

        $organization = $this->currentOrganization();

        $organization->update([
            'n8n_base_url' => $baseUrl,
            'n8n_api_key' => $apiKey,
            'n8n_connected_at' => now(),
        ]);

        $this->refreshWorkflows($organization->id);

        return back()->with('status', 'n8n-connected');
    }

    public function destroy(): RedirectResponse
    {
        $organization = $this->currentOrganization();

        $organization->update([
            'n8n_base_url' => null,
            'n8n_api_key' => null,
            'n8n_connected_at' => null,
        ]);

        $this->workflowCache->forget($organization->id);

        return back()->with('status', 'n8n-disconnected');
    }

    public function refresh(): RedirectResponse
    {
        $this->refreshWorkflows($this->currentOrganization()->id);

        return back();
    }

    /**
     * Branche un workflow n8n en créant le webhook sortant correspondant —
     * l'intérêt d'avoir la clé : l'URL du nœud Webhook est lue dans n8n, pas
     * recopiée à la main.
     */
    public function connectWorkflow(ConnectN8nWorkflowRequest $request, CreateWebhook $createWebhook): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $createWebhook->handle(
            $this->currentOrganization(),
            $user,
            $request->string('webhook_url')->toString(),
            $request->validated('events'),
        );

        return back()->with('status', 'n8n-workflow-connected');
    }

    /**
     * L'ancienne liste est vidée avant de relancer la récupération : la page
     * repasse en chargement plutôt que d'afficher des workflows périmés.
     */
    private function refreshWorkflows(int $organizationId): void
    {
        $this->workflowCache->forget($organizationId);

        RefreshN8nWorkflowsJob::dispatch($organizationId);
    }

    private function currentOrganization(): Organization
    {
        return Organization::query()->findOrFail(app(CurrentOrganization::class)->requireId());
    }
}
