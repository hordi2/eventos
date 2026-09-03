<?php

declare(strict_types=1);

namespace App\Http\Requests\Organizer\Tag;

use Illuminate\Foundation\Http\FormRequest;

final class SaveTagRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:100'],
            'color' => ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ];
    }
}
