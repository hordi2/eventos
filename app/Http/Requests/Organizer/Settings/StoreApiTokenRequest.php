<?php

declare(strict_types=1);

namespace App\Http\Requests\Organizer\Settings;

use Illuminate\Foundation\Http\FormRequest;

final class StoreApiTokenRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
        ];
    }
}
