<?php

declare(strict_types=1);

namespace App\Http\Controllers\Organizer\Settings;

use App\Domain\Organization\Models\Organization;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organizer\Settings\StoreApiTokenRequest;
use App\Http\Requests\Organizer\Settings\StoreWebhookRequest;
use App\Http\Requests\Organizer\Settings\UpdateWebhookRequest;
use App\Jobs\RefreshN8nWorkflowsJob;
use App\Models\User;
use App\Support\Integrations\N8nWorkflowCache;
use App\Support\MultiTenancy\CurrentOrganization;
use App\Support\Webhooks\Actions\CreateWebhook;
use App\Support\Webhooks\Actions\DeleteWebhook;
use App\Support\Webhooks\Actions\UpdateWebhook;
use App\Support\Webhooks\Models\Webhook;
use App\Support\Webhooks\WebhookEvent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class IntegrationController extends Controller
{
    public function __construct(
        private readonly N8nWorkflowCache $workflowCache,
    ) {}

    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        return Inertia::render('Settings/Integrations', [
            'tokens' => $user->tokens()->orderByDesc('created_at')->get()->map(fn ($token): array => [
                'id' => $token->id,
                'name' => $token->name,
                'last_used_at' => $token->last_used_at?->toIso8601String(),
                'created_at' => $token->created_at?->toIso8601String(),
            ]),
            'webhooks' => Webhook::query()->orderByDesc('created_at')->get()->map(fn (Webhook $webhook): array => [
                'id' => $webhook->id,
                'url' => $webhook->url,
                'subscribed_events' => $webhook->subscribed_events,
                'is_active' => $webhook->is_active,
                'last_delivery_at' => $webhook->last_delivery_at?->toIso8601String(),
                'last_delivery_status' => $webhook->last_delivery_status,
            ]),
            'availableEvents' => array_map(
                fn (WebhookEvent $event): array => ['value' => $event->value, 'label' => $event->label()],
                WebhookEvent::cases(),
            ),
            // Guides Zapier/n8n : l'URL de base est calculée côté serveur
            // pour que l'organisateur puisse copier-coller sans deviner le
            // domaine de son instance.
            'apiBaseUrl' => url('/api/v1'),
            'n8n' => $this->n8nState(),
        ]);
    }

    /**
     * État de la connexion n8n, lu uniquement dans le cache : la page ne
     * contacte jamais l'instance du client, qui peut être lente ou arrêtée.
     * Cache vide (première visite, liste expirée, rafraîchissement demandé)
     * : la récupération part en file d'attente et la page se met à jour
     * toute seule quand elle est prête.
     *
     * @return array{connected: bool, base_url: ?string, loading: bool, workflows: list<array{id: string, name: string, active: bool, webhook_url: ?string}>, error: ?string, fetched_at: ?string}
     */
    private function n8nState(): array
    {
        $organizationId = app(CurrentOrganization::class)->id();
        $organization = $organizationId !== null ? Organization::query()->find($organizationId) : null;

        if ($organization?->n8n_base_url === null || $organization->n8n_api_key === null) {
            return ['connected' => false, 'base_url' => null, 'loading' => false, 'workflows' => [], 'error' => null, 'fetched_at' => null];
        }

        $cached = $this->workflowCache->get($organization->id);

        if ($cached === null) {
            RefreshN8nWorkflowsJob::dispatch($organization->id);

            return [
                'connected' => true,
                'base_url' => $organization->n8n_base_url,
                'loading' => true,
                'workflows' => [],
                'error' => null,
                'fetched_at' => null,
            ];
        }

        return [
            'connected' => true,
            'base_url' => $organization->n8n_base_url,
            'loading' => false,
            'workflows' => $cached['workflows'],
            'error' => $cached['error'],
            'fetched_at' => $cached['fetched_at'],
        ];
    }

    public function storeToken(StoreApiTokenRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $token = $user->createToken($request->string('name')->toString());

        return back()->with('plainToken', $token->plainTextToken);
    }

    public function destroyToken(Request $request, int $token): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $user->tokens()->where('id', $token)->delete();

        return back();
    }

    public function storeWebhook(StoreWebhookRequest $request, CreateWebhook $createWebhook): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $webhook = $createWebhook->handle(
            organization: $this->currentOrganization(),
            creator: $user,
            url: $request->string('url')->toString(),
            subscribedEvents: $request->array('subscribed_events'),
        );

        return back()->with('plainSecret', $webhook->secret);
    }

    public function updateWebhook(UpdateWebhookRequest $request, int $webhook, UpdateWebhook $updateWebhook): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $updateWebhook->handle(
            webhook: $this->findWebhook($webhook),
            editor: $user,
            url: $request->string('url')->toString(),
            subscribedEvents: $request->array('subscribed_events'),
            isActive: $request->boolean('is_active'),
        );

        return back();
    }

    public function destroyWebhook(Request $request, int $webhook, DeleteWebhook $deleteWebhook): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $deleteWebhook->handle($this->findWebhook($webhook), $user);

        return back();
    }

    private function currentOrganization(): Organization
    {
        return Organization::query()->findOrFail(app(CurrentOrganization::class)->requireId());
    }

    private function findWebhook(int $id): Webhook
    {
        return Webhook::query()->findOrFail($id);
    }
}
