<?php

declare(strict_types=1);

namespace App\Http\Requests\Organizer\Page;

use Illuminate\Foundation\Http\FormRequest;

final class UpdatePageRequest extends FormRequest
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
            'meta_description' => ['nullable', 'string', 'max:160'],
            'program_items' => ['array'],
            'program_items.*.time' => ['required', 'string', 'max:50'],
            'program_items.*.title' => ['required', 'string', 'max:255'],
            'program_items.*.description' => ['nullable', 'string', 'max:1000'],
            'faq_items' => ['array'],
            'faq_items.*.question' => ['required', 'string', 'max:255'],
            'faq_items.*.answer' => ['required', 'string', 'max:2000'],
        ];
    }
}
