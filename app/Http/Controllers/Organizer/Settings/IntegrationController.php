<?php

declare(strict_types=1);

namespace App\Http\Controllers\Organizer\Settings;

use App\Domain\Organization\Models\Organization;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organizer\Settings\StoreApiTokenRequest;
use App\Http\Requests\Organizer\Settings\StoreWebhookRequest;
use App\Http\Requests\Organizer\Settings\UpdateWebhookRequest;
use App\Models\User;
use App\Support\Integrations\N8nClient;
use App\Support\Integrations\N8nConnectionException;
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
        private readonly N8nClient $n8n,
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
            'n8n' => $this->n8nState($request),
        ]);
    }

    /**
     * État de la connexion n8n, workflows compris quand elle est établie.
     * L'instance appartient au client : si elle ne répond plus (arrêtée,
     * clé révoquée), on affiche le message d'erreur plutôt que de casser
     * toute la page Intégrations.
     *
     * @return array{connected: bool, base_url: ?string, workflows: list<array{id: string, name: string, active: bool, webhook_url: ?string}>, error: ?string}
     */
    private function n8nState(Request $request): array
    {
        $organizationId = app(CurrentOrganization::class)->id();
        $organization = $organizationId !== null ? Organization::query()->find($organizationId) : null;

        if ($organization?->n8n_base_url === null || $organization->n8n_api_key === null) {
            return ['connected' => false, 'base_url' => null, 'workflows' => [], 'error' => null];
        }

        try {
            $workflows = $this->n8n->workflows($organization->n8n_base_url, $organization->n8n_api_key);
        } catch (N8nConnectionException $exception) {
            return [
                'connected' => true,
                'base_url' => $organization->n8n_base_url,
                'workflows' => [],
                'error' => $exception->getMessage(),
            ];
        }

        return [
            'connected' => true,
            'base_url' => $organization->n8n_base_url,
            'workflows' => $workflows,
            'error' => null,
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
