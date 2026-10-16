<?php

declare(strict_types=1);

namespace App\Http\Requests\Organizer\Order;

use Illuminate\Foundation\Http\FormRequest;

final class RefundOrderRequest extends FormRequest
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
            // Vide ou absent : remboursement intégral de ce qu'il reste à
            // rembourser (voir OrderController::refund).
            'amount' => ['nullable', 'string', 'max:20'],
        ];
    }
}
