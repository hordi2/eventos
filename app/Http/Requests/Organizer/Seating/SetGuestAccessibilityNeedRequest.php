<?php

declare(strict_types=1);

namespace App\Http\Requests\Organizer\Seating;

use Illuminate\Foundation\Http\FormRequest;

final class SetGuestAccessibilityNeedRequest extends FormRequest
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
            'needs_accessible_seating' => ['required', 'boolean'],
        ];
    }
}
