<?php

declare(strict_types=1);

namespace App\Http\Requests\Organizer\Seating;

use App\Domain\CheckIn\Models\SeatingTableShape;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreSeatingTableRequest extends FormRequest
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
            'name' => ['nullable', 'string', 'max:100'],
            'shape' => ['required', Rule::enum(SeatingTableShape::class)],
            'capacity' => ['required', 'integer', 'min:1', 'max:200'],
        ];
    }
}
