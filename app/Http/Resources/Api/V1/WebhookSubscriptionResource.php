<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Support\Webhooks\Models\Webhook;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Webhook
 */
final class WebhookSubscriptionResource extends JsonResource
{
    /**
     * Le secret de signature n'est jamais renvoyé ici : Zapier et n8n
     * vérifient la provenance par l'URL secrète qu'ils génèrent eux-mêmes,
     * et un abonnement créé par API n'a pas d'écran où l'afficher une seule
     * fois comme la page Intégrations.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'target_url' => $this->url,
            'events' => $this->subscribed_events,
        ];
    }
}
