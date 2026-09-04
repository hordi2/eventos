<?php

declare(strict_types=1);

namespace App\Http\Requests\Organizer\Seating;

use App\Domain\CheckIn\Models\SeatingTableShape;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateSeatingTableRequest extends FormRequest
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
            'name' => ['sometimes', 'string', 'max:100'],
            'shape' => ['sometimes', Rule::enum(SeatingTableShape::class)],
            'capacity' => ['sometimes', 'integer', 'min:1', 'max:200'],
            'position_x' => ['sometimes', 'numeric'],
            'position_y' => ['sometimes', 'numeric'],
            'width' => ['sometimes', 'numeric', 'min:20'],
            'height' => ['sometimes', 'numeric', 'min:20'],
            'rotation' => ['sometimes', 'numeric'],
        ];
    }
}
