<?php

declare(strict_types=1);

namespace App\Http\Requests\Organizer\CheckIn;

use Illuminate\Foundation\Http\FormRequest;

final class ScanTicketRequest extends FormRequest
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
            'token' => ['required', 'string'],
        ];
    }
}
