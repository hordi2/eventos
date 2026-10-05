<?php

declare(strict_types=1);

namespace App\Http\Requests\Organizer\Template;

use Illuminate\Foundation\Http\FormRequest;

final class UseEventTemplateRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:255'],
            'start_at' => ['required', 'date'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.required' => 'Donnez un titre à votre événement.',
            'start_at.required' => 'Indiquez quand il aura lieu.',
        ];
    }
}
