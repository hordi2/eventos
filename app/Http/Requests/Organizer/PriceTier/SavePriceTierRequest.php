<?php

declare(strict_types=1);

namespace App\Http\Requests\Organizer\PriceTier;

use Illuminate\Foundation\Http\FormRequest;

final class SavePriceTierRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'amount_minor' => ['required', 'integer', 'min:0'],
            'quantity' => ['nullable', 'integer', 'min:1'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date'],
        ];
    }
}
