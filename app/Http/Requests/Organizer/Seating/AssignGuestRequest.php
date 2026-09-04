<?php

declare(strict_types=1);

namespace App\Http\Requests\Organizer\Seating;

use Illuminate\Foundation\Http\FormRequest;

final class AssignGuestRequest extends FormRequest
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
            'guest_type' => ['required', 'in:attendee,ticket'],
            'guest_id' => ['required', 'integer'],
        ];
    }
}
