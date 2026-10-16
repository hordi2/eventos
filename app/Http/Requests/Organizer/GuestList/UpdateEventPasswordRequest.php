<?php

declare(strict_types=1);

namespace App\Http\Requests\Organizer\GuestList;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Le droit (modifier l'événement) est vérifié par SetEventPassword. Un mot
 * de passe vide (null) retire la protection, voir cette action.
 */
final class UpdateEventPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Une chaîne vide signifie « retirer la protection », jamais un mot de
     * passe de longueur zéro — sans quoi min:4 la rejetterait à tort.
     */
    protected function prepareForValidation(): void
    {
        if ($this->input('password') === '') {
            $this->merge(['password' => null]);
        }
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            // 72 : la limite de bcrypt, au-delà silencieusement tronqué.
            'password' => ['nullable', 'string', 'min:4', 'max:72'],
        ];
    }
}
