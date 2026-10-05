<?php

declare(strict_types=1);

namespace App\Http\Requests\Guest;

use Illuminate\Foundation\Http\FormRequest;

final class JoinAttendeeDirectoryRequest extends FormRequest
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
            'join' => ['required', 'boolean'],
            // Une ligne, pas une biographie : c'est un annuaire.
            'headline' => ['nullable', 'string', 'max:120'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'headline.max' => 'Votre présentation tient en 120 caractères.',
        ];
    }
}
