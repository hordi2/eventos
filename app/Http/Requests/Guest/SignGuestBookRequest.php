<?php

declare(strict_types=1);

namespace App\Http\Requests\Guest;

use Illuminate\Foundation\Http\FormRequest;

final class SignGuestBookRequest extends FormRequest
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
            'author_name' => ['required', 'string', 'max:120'],
            'message' => ['required', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'author_name.required' => __('Indiquez votre nom.'),
            'message.required' => __('Écrivez votre message.'),
            'message.max' => __('Votre message ne peut pas dépasser 1000 caractères.'),
        ];
    }
}
