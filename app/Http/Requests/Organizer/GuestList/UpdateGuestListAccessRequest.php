<?php

declare(strict_types=1);

namespace App\Http\Requests\Organizer\GuestList;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Le droit (modifier l'événement) est vérifié par UpdateEventGuestListAccess.
 */
final class UpdateGuestListAccessRequest extends FormRequest
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
            'closed' => ['required', 'boolean'],
        ];
    }
}
