<?php

declare(strict_types=1);

namespace App\Http\Requests\Organizer\Ticketing;

use App\Domain\Ticketing\Models\PromoCode;
use App\Domain\Ticketing\Models\PromoCodeKind;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class SavePromoCodeRequest extends FormRequest
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
            // Lettres, chiffres et tirets : un code se dicte au téléphone.
            'code' => ['required', 'string', 'max:40', 'regex:/^[A-Z0-9\-]+$/'],
            'kind' => ['required', Rule::enum(PromoCodeKind::class)],
            'percent' => ['required_if:kind,percent', 'nullable', 'integer', 'min:1', 'max:100'],
            'amount_minor' => ['required_if:kind,amount', 'nullable', 'integer', 'min:1'],
            'max_uses' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.regex' => 'Un code promo ne contient que des lettres, des chiffres et des tirets.',
            'percent.required_if' => 'Indiquez le pourcentage de réduction.',
            'amount_minor.required_if' => 'Indiquez le montant de la réduction.',
            'ends_at.after' => 'La fin doit venir après le début.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('code'))) {
            $this->merge(['code' => PromoCode::normalize($this->string('code')->toString())]);
        }
    }
}
