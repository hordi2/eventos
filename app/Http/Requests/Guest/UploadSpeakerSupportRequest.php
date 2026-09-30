<?php

declare(strict_types=1);

namespace App\Http\Requests\Guest;

use App\Support\Events\PresentSpeakerPortal;
use Illuminate\Foundation\Http\FormRequest;

final class UploadSpeakerSupportRequest extends FormRequest
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
        $extensions = implode(',', PresentSpeakerPortal::SUPPORT_EXTENSIONS);

        return [
            // Type réel vérifié, pas seulement l'extension (§7 du CLAUDE.md).
            'support' => [
                'required',
                'file',
                'max:'.(PresentSpeakerPortal::SUPPORT_MAX_SIZE_MB * 1024),
                "mimes:{$extensions}",
                "extensions:{$extensions}",
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $refused = __("Ce format n'est pas accepté. Envoyez un PDF, une présentation ou une image.");

        return [
            'support.required' => __('Choisissez le fichier à déposer.'),
            'support.uploaded' => __("L'envoi a échoué : le fichier dépasse sans doute la taille autorisée."),
            'support.max' => __('Ce fichier dépasse :size Mo.', ['size' => PresentSpeakerPortal::SUPPORT_MAX_SIZE_MB]),
            'support.mimes' => $refused,
            'support.extensions' => $refused,
        ];
    }
}
