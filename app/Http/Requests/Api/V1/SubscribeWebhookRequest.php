<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Support\Webhooks\WebhookEvent;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * `target_url` reprend le nom de champ attendu par la plateforme Zapier
 * (REST Hooks) ; n8n envoie ce qu'on lui demande, on s'aligne donc sur la
 * convention la plus contrainte des deux.
 */
final class SubscribeWebhookRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // https uniquement : la charge utile contient des données
            // personnelles d'invités (nom, e-mail), jamais en clair sur le
            // réseau.
            'target_url' => ['required', 'url:https', 'max:2048'],
            'event' => ['required', Rule::enum(WebhookEvent::class)],
        ];
    }
}
