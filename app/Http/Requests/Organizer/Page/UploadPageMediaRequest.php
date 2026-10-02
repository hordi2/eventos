<?php

declare(strict_types=1);

namespace App\Http\Requests\Organizer\Page;

use Illuminate\Foundation\Http\FormRequest;

final class UploadPageMediaRequest extends FormRequest
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
            // 40 Mo : un mot d'accueil d'une minute en vidéo y tient large.
            'media' => [
                'required',
                'file',
                'mimetypes:audio/mpeg,audio/mp4,audio/aac,audio/ogg,audio/wav,audio/x-wav,video/mp4,video/webm',
                'extensions:mp3,m4a,aac,ogg,oga,wav,mp4,webm',
                'max:40960',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'media.mimetypes' => 'Déposez un fichier audio (MP3, M4A, WAV, OGG) ou vidéo (MP4, WebM).',
            'media.extensions' => 'Déposez un fichier audio (MP3, M4A, WAV, OGG) ou vidéo (MP4, WebM).',
            'media.max' => 'Ce fichier dépasse 40 Mo.',
        ];
    }
}
