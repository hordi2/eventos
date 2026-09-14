<?php

declare(strict_types=1);

namespace App\Http\Requests\Organizer\Form;

use Illuminate\Foundation\Http\FormRequest;

/**
 * L'autorisation (update du formulaire) est vérifiée par SaveFormThemeImage.
 * « mimes » contrôle le type réel du contenu, pas seulement l'extension.
 */
final class UploadFormThemeImageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'image' => ['required', 'file', 'mimes:png,jpg,jpeg', 'max:2048'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'image.mimes' => "L'image doit être au format PNG ou JPG.",
            'image.max' => "L'image ne doit pas dépasser 2 Mo.",
        ];
    }
}
