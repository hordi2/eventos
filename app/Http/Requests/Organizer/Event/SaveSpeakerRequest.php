<?php

declare(strict_types=1);

namespace App\Http\Requests\Organizer\Event;

use Illuminate\Foundation\Http\FormRequest;

final class SaveSpeakerRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'role' => ['nullable', 'string', 'max:255'],
            'company' => ['nullable', 'string', 'max:255'],
            'bio' => ['nullable', 'string', 'max:2000'],
            'website_url' => ['nullable', 'url', 'max:2048'],
            'linkedin_url' => ['nullable', 'url', 'max:2048'],
            'session_ids' => ['array'],
            'session_ids.*' => ['integer'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => "Indiquez le nom de l'intervenant.",
            'website_url.url' => 'Le site web doit être une adresse complète, avec https://.',
            'linkedin_url.url' => 'Le profil LinkedIn doit être une adresse complète, avec https://.',
        ];
    }
}
