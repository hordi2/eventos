<?php

declare(strict_types=1);

namespace App\Http\Requests\Guest;

use Illuminate\Foundation\Http\FormRequest;

final class RespondToSpeakerSlotRequest extends FormRequest
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
            'response' => ['required', 'in:accept,decline'],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'response.required' => __('Indiquez si vous serez présent.'),
            'response.in' => __('Indiquez si vous serez présent.'),
            'note.max' => __('Votre message ne peut pas dépasser 1000 caractères.'),
        ];
    }
}
