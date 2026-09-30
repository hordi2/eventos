<?php

declare(strict_types=1);

namespace App\Http\Requests\Organizer\Event;

use Illuminate\Foundation\Http\FormRequest;

final class SaveProposalCallRequest extends FormRequest
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
            'is_open' => ['required', 'boolean'],
            'intro' => ['nullable', 'string', 'max:5000'],
            'closes_at' => ['nullable', 'date'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'closes_at.date' => "La date limite n'est pas une date valide.",
        ];
    }
}
