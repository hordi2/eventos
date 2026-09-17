<?php

declare(strict_types=1);

namespace App\Domain\Form\Support;

use App\Domain\Form\Models\FieldType;
use App\Domain\Form\Models\FileScanStatus;
use App\Domain\Form\Models\FormVersion;
use App\Domain\Form\Models\RegistrationFile;
use Illuminate\Validation\ValidationException;

/**
 * Fichiers cités par des réponses (bloc « Fichier joint ») : chacun doit
 * exister pour cette question, ne pas avoir été refusé par l'antivirus, et
 * n'appartenir à aucune autre inscription — une référence ne passe pas d'un
 * invité à l'autre.
 */
final class ValidateRegistrationFiles
{
    /**
     * @param  array<string, mixed>  $answers
     * @param  array<string, array{visible: bool, required: bool}>  $visibility
     * @param  int|null  $registrationId  inscription modifiée ; null pour une nouvelle inscription
     * @return array<string, string> message par clé de question
     */
    public function errors(FormVersion $version, array $answers, array $visibility, ?int $registrationId = null): array
    {
        $errors = [];

        foreach ($version->fields as $field) {
            $token = $answers[$field->key] ?? null;

            if ($field->type !== FieldType::FileUpload || ! ($visibility[$field->key]['visible'] ?? false) || ! is_string($token) || $token === '') {
                continue;
            }

            $file = RegistrationFile::query()->where('token', $token)->where('form_field_id', $field->id)->first();

            if ($file === null || ($file->registration_id !== null && $file->registration_id !== $registrationId)) {
                $errors[$field->key] = "Ce fichier n'est plus disponible : envoyez-le à nouveau.";
            } elseif ($file->scan_status === FileScanStatus::Infected) {
                $errors[$field->key] = "Ce fichier a été refusé par l'analyse antivirus : envoyez-en un autre.";
            }
        }

        return $errors;
    }

    /**
     * @param  array<string, mixed>  $answers
     * @param  array<string, array{visible: bool, required: bool}>  $visibility
     *
     * @throws ValidationException
     */
    public function handle(FormVersion $version, array $answers, array $visibility, ?int $registrationId = null): void
    {
        $errors = $this->errors($version, $answers, $visibility, $registrationId);

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }
}
