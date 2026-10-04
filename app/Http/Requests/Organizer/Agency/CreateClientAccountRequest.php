<?php

declare(strict_types=1);

namespace App\Http\Requests\Organizer\Agency;

use Illuminate\Foundation\Http\FormRequest;

final class CreateClientAccountRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:120'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Donnez un nom au compte de votre client.',
        ];
    }
}
