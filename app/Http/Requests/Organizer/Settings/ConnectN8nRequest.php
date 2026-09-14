<?php

declare(strict_types=1);

namespace App\Http\Requests\Organizer\Settings;

use Illuminate\Foundation\Http\FormRequest;

final class ConnectN8nRequest extends FormRequest
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
            // https uniquement : la clé API n8n transite dans l'en-tête de
            // chaque appel, jamais en clair sur le réseau.
            'base_url' => ['required', 'url:https', 'max:255'],
            'api_key' => ['required', 'string', 'max:2048'],
        ];
    }
}
