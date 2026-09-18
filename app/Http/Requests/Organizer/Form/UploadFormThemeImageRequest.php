<?php

declare(strict_types=1);

namespace App\Http\Requests\Organizer\Form;

use App\Support\MultiTenancy\CurrentOrganization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * L'autorisation (update du formulaire) est vérifiée par SaveFormThemeImage.
 * « mimes » contrôle le type réel du contenu, pas seulement l'extension.
 * « image_id » désigne une image déjà présente dans « Mes images ».
 */
final class UploadFormThemeImageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'image' => ['required_without:image_id', 'file', 'mimes:png,jpg,jpeg', 'max:2048'],
            'image_id' => [
                'required_without:image',
                'integer',
                Rule::exists('organization_images', 'id')
                    ->where('organization_id', app(CurrentOrganization::class)->requireId())
                    ->whereNull('deleted_at'),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'image.required_without' => 'Choisissez une image à envoyer ou une image de votre bibliothèque.',
            'image.mimes' => "L'image doit être au format PNG ou JPG.",
            'image.max' => "L'image ne doit pas dépasser 2 Mo.",
            'image_id.exists' => "Cette image n'est plus dans votre bibliothèque.",
        ];
    }
}
