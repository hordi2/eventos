<?php

declare(strict_types=1);

namespace App\Http\Requests\Organizer\CheckIn;

use Illuminate\Foundation\Http\FormRequest;

final class RecordCheckInRequest extends FormRequest
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
            'id' => ['required', 'integer'],
        ];
    }
}
