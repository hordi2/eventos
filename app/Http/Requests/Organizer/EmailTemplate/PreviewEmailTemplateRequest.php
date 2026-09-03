<?php

declare(strict_types=1);

namespace App\Http\Requests\Organizer\EmailTemplate;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class PreviewEmailTemplateRequest extends FormRequest
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
            'contact_id' => ['required', 'integer', Rule::exists('contacts', 'id')],
            'event_id' => ['nullable', 'integer', Rule::exists('events', 'id')],
        ];
    }
}
