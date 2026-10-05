<?php

declare(strict_types=1);

namespace App\Http\Requests\Guest;

use Illuminate\Foundation\Http\FormRequest;

final class ProposeAttendeeMeetingRequest extends FormRequest
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
            'guest_registration_id' => ['required', 'integer'],
            // Heure locale de l'événement : le contrôleur la convertit en UTC.
            'starts_at' => ['required', 'date'],
            'duration_minutes' => ['nullable', 'integer', 'min:5', 'max:240'],
            'place' => ['nullable', 'string', 'max:120'],
            'message' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'starts_at.required' => 'Indiquez quand vous proposez de vous voir.',
            'message.max' => 'Votre mot tient en 500 caractères.',
        ];
    }
}
