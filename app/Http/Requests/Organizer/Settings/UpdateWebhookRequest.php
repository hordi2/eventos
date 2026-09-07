<?php

declare(strict_types=1);

namespace App\Http\Requests\Organizer\Settings;

use App\Support\Webhooks\WebhookEvent;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateWebhookRequest extends FormRequest
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
            'url' => ['required', 'url', 'max:2048'],
            'subscribed_events' => ['required', 'array', 'min:1'],
            'subscribed_events.*' => [Rule::in(WebhookEvent::values())],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
