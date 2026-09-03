<?php

declare(strict_types=1);

namespace App\Http\Requests\Organizer\TicketType;

use App\Domain\Ticketing\Models\VatMode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateTicketTypeRequest extends FormRequest
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
            'description' => ['nullable', 'string', 'max:2000'],
            'min_per_order' => ['required', 'integer', 'min:1'],
            'max_per_order' => ['nullable', 'integer', 'min:1'],
            'total_quantity' => ['nullable', 'integer', 'min:0'],
            'vat_mode' => ['required', 'string', Rule::enum(VatMode::class)],
            'vat_rate_bp' => ['required', 'integer', 'min:0'],
            'fees_absorbed' => ['required', 'boolean'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
