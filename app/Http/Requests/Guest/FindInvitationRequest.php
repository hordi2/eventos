<?php

declare(strict_types=1);

namespace App\Http\Requests\Guest;

use Illuminate\Foundation\Http\FormRequest;

/**
 * « Retrouver mon invitation » : une adresse e-mail ou un numéro WhatsApp.
 * Le débit est limité sur la route, contre les essais en série.
 */
final class FindInvitationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'identifier' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'identifier.required' => 'Indiquez votre adresse e-mail ou votre numéro WhatsApp.',
        ];
    }
}
