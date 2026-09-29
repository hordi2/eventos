<?php

declare(strict_types=1);

namespace App\Http\Requests\Organizer\Page;

use Illuminate\Foundation\Http\FormRequest;

final class UploadPageImageRequest extends FormRequest
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
            // Type réel vérifié, pas seulement l'extension (§7 du CLAUDE.md).
            'image' => ['required', 'file', 'mimes:png,jpg,jpeg,webp', 'extensions:png,jpg,jpeg,webp', 'max:8192'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'image.mimes' => 'Choisissez une image PNG, JPG ou WebP.',
            'image.extensions' => 'Choisissez une image PNG, JPG ou WebP.',
            'image.max' => 'Cette image dépasse 8 Mo.',
        ];
    }
}
