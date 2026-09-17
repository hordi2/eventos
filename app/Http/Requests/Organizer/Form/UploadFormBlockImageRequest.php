<?php

declare(strict_types=1);

namespace App\Http\Requests\Organizer\Form;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Image d'un bloc « Texte, image, vidéo ». La limite de 8 Mo porte sur le
 * fichier envoyé : il est redimensionné juste après (ResizeUploadedImage),
 * l'organisateur n'a donc pas à préparer son image.
 */
final class UploadFormBlockImageRequest extends FormRequest
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
            'image' => ['required', 'file', 'mimes:png,jpg,jpeg,webp', 'extensions:png,jpg,jpeg,webp', 'max:8192'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'image.mimes' => 'Choisissez une image PNG, JPG ou WEBP.',
            'image.extensions' => 'Choisissez une image PNG, JPG ou WEBP.',
            'image.max' => 'Cette image dépasse 8 Mo.',
        ];
    }
}
