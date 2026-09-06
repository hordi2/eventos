<?php

declare(strict_types=1);

namespace App\Http\Requests\Organizer\Page;

use Illuminate\Foundation\Http\FormRequest;

final class UploadPageBannerRequest extends FormRequest
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
            'banner' => ['required', 'file', 'mimes:png,jpg,jpeg', 'max:4096'],
        ];
    }
}
