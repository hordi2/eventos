<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Domain\Form\Models\Registration;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Mêmes champs que le webhook registration.created/updated
 * (DispatchRegistrationWebhooks) : une intégration qui lit l'un reconnaît
 * l'autre. Sans téléphone ni réponses de formulaire — le versionnement
 * d'un formulaire (§4.7 du CLAUDE.md) mérite son propre ticket avant
 * d'exposer des réponses par API.
 *
 * @property Registration $resource
 */
final class RegistrationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'event_id' => $this->resource->event_id,
            'status' => $this->resource->status->value,
            'email' => $this->resource->email,
            'first_name' => $this->resource->first_name,
            'last_name' => $this->resource->last_name,
            'submitted_at' => $this->resource->created_at?->toIso8601String(),
            'cancelled_at' => $this->resource->cancelled_at?->toIso8601String(),
        ];
    }
}
