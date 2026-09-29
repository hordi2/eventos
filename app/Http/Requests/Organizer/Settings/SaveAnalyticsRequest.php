<?php

declare(strict_types=1);

namespace App\Http\Requests\Organizer\Settings;

use Illuminate\Foundation\Http\FormRequest;

final class SaveAnalyticsRequest extends FormRequest
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
            // Identifiant GA4, de la forme G-XXXXXXXX ; vide retire la mesure.
            'ga4_measurement_id' => ['nullable', 'string', 'max:20', 'regex:/^G-[A-Z0-9]{4,15}$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'ga4_measurement_id.regex' => 'Un identifiant GA4 ressemble à G-XXXXXXXX.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('ga4_measurement_id'))) {
            $this->merge(['ga4_measurement_id' => mb_strtoupper(trim($this->string('ga4_measurement_id')->toString()))]);
        }
    }
}
