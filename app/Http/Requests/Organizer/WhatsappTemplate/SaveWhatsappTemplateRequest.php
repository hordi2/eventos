<?php

declare(strict_types=1);

namespace App\Http\Requests\Organizer\WhatsappTemplate;

use Illuminate\Foundation\Http\FormRequest;

final class SaveWhatsappTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'provider_template_sid' => ['required', 'string', 'max:64'],
            'language' => ['required', 'string', 'max:10'],
            'category' => ['nullable', 'string', 'max:32'],
            'variable_mapping' => ['present', 'array'],
            'variable_mapping.*' => ['required', 'string', 'max:64'],
        ];
    }
}
