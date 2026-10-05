<?php

declare(strict_types=1);

namespace App\Http\Requests\Guest;

use App\Domain\Form\Models\TravelMode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class DeclareTravelRequest extends FormRequest
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
            'travel_mode' => ['required', Rule::enum(TravelMode::class)],
            // Un aller simple, en kilomètres. Le calcul double pour le retour.
            'travel_distance_km' => ['nullable', 'integer', 'min:0', 'max:20000'],
            'travel_city' => ['nullable', 'string', 'max:80'],
            'carpool_role' => ['nullable', Rule::in(['offers', 'seeks'])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'travel_mode.required' => 'Dites-nous comment vous venez.',
            'travel_distance_km.max' => 'Cette distance paraît trop grande.',
        ];
    }
}
