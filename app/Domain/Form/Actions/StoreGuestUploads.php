<?php

declare(strict_types=1);

namespace App\Domain\Form\Actions;

use App\Domain\Form\Models\FieldType;
use App\Domain\Form\Models\FormVersion;
use App\Domain\Form\Support\FileUploadAnswer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;

/**
 * Fichiers joints reçus avec une page de réponses : chacun est contrôlé
 * (type réel, extension, taille) puis rangé en quarantaine, et sa référence
 * prend sa place dans les réponses. Rangé avant la validation du reste de la
 * page, un fichier accepté n'est pas à renvoyer quand une autre réponse est
 * à corriger ; un fichier refusé rend un message, affiché avec les autres.
 */
final class StoreGuestUploads
{
    public function __construct(
        private readonly StoreRegistrationFile $storeRegistrationFile,
    ) {}

    /**
     * @param  array<array-key, mixed>  $uploads  fichiers reçus sous FileUploadAnswer::INPUT_KEY, par clé de question
     * @return array{tokens: array<string, string>, errors: array<string, string>}
     */
    public function handle(FormVersion $version, int $organizationId, int $eventId, array $uploads, ?int $draftId = null): array
    {
        $tokens = [];
        $errors = [];

        foreach ($version->fields as $field) {
            $upload = $uploads[$field->key] ?? null;

            if ($field->type !== FieldType::FileUpload || ! $upload instanceof UploadedFile) {
                continue;
            }

            $config = $field->config ?? [];
            $validator = Validator::make(
                ['fichier' => $upload],
                ['fichier' => FileUploadAnswer::uploadRules($config)],
                FileUploadAnswer::uploadMessages($config),
            );

            if ($validator->fails()) {
                $errors[$field->key] = (string) $validator->errors()->first('fichier');

                continue;
            }

            $tokens[$field->key] = $this->storeRegistrationFile->handle($organizationId, $eventId, $field, $upload, $draftId)->token;
        }

        return ['tokens' => $tokens, 'errors' => $errors];
    }
}
