<?php

declare(strict_types=1);

namespace App\Http\Requests\Organizer\CheckIn;

use Illuminate\Foundation\Http\FormRequest;

final class KioskCodeRequest extends FormRequest
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
            'code' => ['required', 'digits:4'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.required' => 'Choisissez un code à quatre chiffres.',
            'code.digits' => 'Le code doit compter quatre chiffres.',
        ];
    }
}
