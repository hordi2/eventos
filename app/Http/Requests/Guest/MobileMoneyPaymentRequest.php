<?php

declare(strict_types=1);

namespace App\Http\Requests\Guest;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class MobileMoneyPaymentRequest extends FormRequest
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
            // Indicatifs à 3 chiffres uniquement (Afrique francophone
            // prioritaire, §1 CLAUDE.md) — voir MobileMoneyChargeRequest.
            'country_code' => ['required', Rule::in(['243', '242', '237', '225', '221'])],
            'phone_number' => ['required', 'string', 'max:20'],
            'network' => ['required', Rule::in(['MTN', 'ORANGE', 'MOOV', 'AIRTEL'])],
        ];
    }
}
