<?php

declare(strict_types=1);

namespace App\Http\Requests\Organizer\TicketType;

use App\Domain\Ticketing\Models\VatMode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class SaveTicketTypeRequest extends FormRequest
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
            'is_free' => ['required', 'boolean'],
            'currency' => ['required', 'string', 'size:3'],
            'min_per_order' => ['required', 'integer', 'min:1'],
            'max_per_order' => ['nullable', 'integer', 'min:1'],
            'total_quantity' => ['nullable', 'integer', 'min:0'],
            'vat_mode' => ['required', 'string', Rule::enum(VatMode::class)],
            'vat_rate_bp' => ['required', 'integer', 'min:0'],
            // Choix explicite exigé par CreateTicketType (M5.1) : "required"
            // plutôt que "nullable", jamais de valeur par défaut silencieuse.
            'fees_absorbed' => ['required', 'boolean'],
            'tiers' => ['array'],
            'tiers.*.name' => ['required', 'string', 'max:255'],
            'tiers.*.amount_minor' => ['required', 'integer', 'min:0'],
            'tiers.*.quantity' => ['nullable', 'integer', 'min:1'],
            'tiers.*.starts_at' => ['nullable', 'date'],
            'tiers.*.ends_at' => ['nullable', 'date'],
        ];
    }
}
