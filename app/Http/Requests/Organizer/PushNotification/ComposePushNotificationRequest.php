<?php

declare(strict_types=1);

namespace App\Http\Requests\Organizer\PushNotification;

use Illuminate\Foundation\Http\FormRequest;

final class ComposePushNotificationRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:80'],
            'body' => ['required', 'string', 'max:200'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.required' => 'Le titre est obligatoire.',
            'title.max' => 'Le titre ne doit pas dépasser :max caractères.',
            'body.required' => 'Le message est obligatoire.',
            'body.max' => 'Le message ne doit pas dépasser :max caractères.',
        ];
    }
}
