<?php

declare(strict_types=1);

namespace App\Http\Requests\Organizer\WhatsappTemplate;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class SendTestWhatsappRequest extends FormRequest
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
            'contact_id' => ['required', 'integer', Rule::exists('contacts', 'id')],
            'event_id' => ['nullable', 'integer', Rule::exists('events', 'id')],
            // Format E.164 uniquement : la validation "vrai numéro
            // exploitable" est déjà celle utilisée pour l'import (T-041) et
            // n'a pas besoin d'être dupliquée pour un envoi de test.
            'to_phone_e164' => ['required', 'string', 'regex:/^\+[1-9]\d{6,14}$/'],
        ];
    }
}
