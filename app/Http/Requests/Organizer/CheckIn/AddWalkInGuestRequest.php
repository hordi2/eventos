<?php

declare(strict_types=1);

namespace App\Http\Requests\Organizer\CheckIn;

use Illuminate\Foundation\Http\FormRequest;

final class AddWalkInGuestRequest extends FormRequest
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
            'ticket_type_id' => ['required', 'integer'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email:rfc'],
            'phone' => ['nullable', 'string', 'max:32'],
        ];
    }
}
