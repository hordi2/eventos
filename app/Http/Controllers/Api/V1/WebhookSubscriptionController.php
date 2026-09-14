<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Organization\Models\Organization;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\SubscribeWebhookRequest;
use App\Http\Resources\Api\V1\WebhookSubscriptionResource;
use App\Models\User;
use App\Support\Webhooks\Actions\CreateWebhook;
use App\Support\Webhooks\Actions\DeleteWebhook;
use App\Support\Webhooks\Models\Webhook;
use App\Support\Webhooks\WebhookEvent;
use App\Support\Webhooks\WebhookSamplePayload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Abonnements REST Hooks : Zapier (et n8n via un nœud HTTP) crée son
 * abonnement au moment où l'utilisateur active un Zap, et le supprime quand
 * il le désactive. Les abonnements créés ici sont de vrais Webhook, donc
 * livrés par la même mécanique signée que ceux créés à la main depuis
 * Paramètres → Intégrations, et visibles au même endroit.
 */
final class WebhookSubscriptionController extends Controller
{
    public function store(SubscribeWebhookRequest $request, CreateWebhook $createWebhook): JsonResponse
    {
        $webhook = $createWebhook->handle(
            $this->organization($request),
            $this->user($request),
            $request->string('target_url')->toString(),
            [$request->string('event')->toString()],
        );

        return WebhookSubscriptionResource::make($webhook)->response()->setStatusCode(201);
    }

    public function destroy(Request $request, int $webhook, DeleteWebhook $deleteWebhook): JsonResponse
    {
        // Jamais de route-model binding implicite sur un modèle cloisonné :
        // SubstituteBindings s'exécute avant resolve-api-organization, donc
        // avant que le contexte multi-tenant n'existe.
        $model = Webhook::query()->findOrFail($webhook);

        $deleteWebhook->handle($model, $this->user($request));

        return response()->json(status: 204);
    }

    /**
     * Échantillon utilisé par Zapier/n8n pour proposer les champs à mapper
     * tant qu'aucun événement réel n'a été livré.
     */
    public function sample(Request $request): JsonResponse
    {
        $event = WebhookEvent::tryFrom($request->string('event')->toString());

        if ($event === null) {
            return response()->json([
                'message' => 'Événement inconnu.',
                'supported_events' => WebhookEvent::values(),
            ], 422);
        }

        return response()->json([WebhookSamplePayload::for($event)]);
    }

    private function organization(Request $request): Organization
    {
        /** @var Organization $organization */
        $organization = $request->attributes->get('apiOrganization');

        return $organization;
    }

    private function user(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
