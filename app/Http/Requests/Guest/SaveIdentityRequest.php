<?php

declare(strict_types=1);

namespace App\Http\Requests\Guest;

use Illuminate\Foundation\Http\FormRequest;

final class SaveIdentityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            // Format seulement (RFC), pas de vérification DNS/MX : contrairement
            // au champ "E-mail" du constructeur de formulaire (T-021, M2.1),
            // ce bloc identité fixe ne doit jamais dépendre de la résolution
            // DNS en sortie — recours réseau que cet environnement sandboxé a
            // révélé peu fiable même pour un domaine qui résout par ailleurs.
            'email' => ['required', 'email:rfc'],
            'first_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32'],
        ];
    }
}
