<?php

declare(strict_types=1);

namespace App\Http\Requests\Organizer\Agency;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateClientResalePriceRequest extends FormRequest
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
            // Vide ou absent : plus de prix de revente renseigné pour ce
            // compte (voir AgencyController::updateResalePrice).
            'amount' => ['nullable', 'string', 'max:20'],
            'currency' => ['required_with:amount', 'string', 'size:3'],
        ];
    }
}
