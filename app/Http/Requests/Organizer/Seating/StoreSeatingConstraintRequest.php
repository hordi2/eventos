<?php

declare(strict_types=1);

namespace App\Http\Requests\Organizer\Seating;

use App\Domain\CheckIn\Models\SeatingConstraintType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreSeatingConstraintRequest extends FormRequest
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
            'guest_a_type' => ['required', 'in:attendee,ticket'],
            'guest_a_id' => ['required', 'integer'],
            'guest_b_type' => ['required', 'in:attendee,ticket'],
            'guest_b_id' => ['required', 'integer'],
            'type' => ['required', Rule::enum(SeatingConstraintType::class)],
        ];
    }
}
