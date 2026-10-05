<?php

declare(strict_types=1);

namespace App\Http\Requests\Organizer\Assistant;

use DateTimeZone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class DraftEventRequest extends FormRequest
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
            'sentence' => ['required', 'string', 'min:10', 'max:2000'],
            'timezone' => ['nullable', 'string', Rule::in(DateTimeZone::listIdentifiers())],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'sentence.required' => 'Décrivez votre événement en une phrase.',
            'sentence.min' => 'Donnez un peu plus de détails.',
        ];
    }
}
