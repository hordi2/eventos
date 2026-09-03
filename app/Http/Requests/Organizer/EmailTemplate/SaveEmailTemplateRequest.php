<?php

declare(strict_types=1);

namespace App\Http\Requests\Organizer\EmailTemplate;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class SaveEmailTemplateRequest extends FormRequest
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
            'subject' => ['required', 'string', 'max:255'],
            'blocks' => ['present', 'array'],
            'blocks.*.type' => ['required', 'string', Rule::in(['heading', 'text', 'image', 'button', 'divider', 'spacer'])],
            'blocks.*.text' => ['nullable', 'string', 'max:5000'],
            'blocks.*.html' => ['nullable', 'string', 'max:20000'],
            'blocks.*.url' => ['nullable', 'string', 'max:2048'],
            'blocks.*.alt' => ['nullable', 'string', 'max:255'],
            'blocks.*.height' => ['nullable', 'integer', 'min:0', 'max:400'],
        ];
    }
}
