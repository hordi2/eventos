<?php

declare(strict_types=1);

namespace App\Http\Requests\Organizer\CheckIn;

use Illuminate\Foundation\Http\FormRequest;

final class UploadBadgeLogoRequest extends FormRequest
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
            'logo' => ['required', 'file', 'mimes:png,jpg,jpeg', 'max:2048'],
        ];
    }
}
