<?php

declare(strict_types=1);

namespace App\Domain\Form\Actions;

use App\Domain\Form\Models\FieldType;
use App\Domain\Form\Models\FormVersion;
use App\Domain\Form\Models\Registration;
use App\Domain\Form\Models\RegistrationAnswer;
use App\Domain\Form\Models\RegistrationFile;
use App\Domain\Form\Support\FileUploadAnswer;

/**
 * Rattache à l'inscription les fichiers cités par ses réponses, et supprime
 * ceux qu'elle ne cite plus (remplacés par l'invité, ou question devenue
 * masquée). Appelé une fois les réponses écrites, à l'inscription comme à
 * sa modification.
 */
final class SyncRegistrationFiles
{
    public function __construct(
        private readonly DeleteRegistrationFile $deleteRegistrationFile,
    ) {}

    public function handle(Registration $registration, FormVersion $version): void
    {
        $fieldIds = $version->fields->filter(fn ($field): bool => $field->type === FieldType::FileUpload)->pluck('id');

        if ($fieldIds->isEmpty()) {
            return;
        }

        $tokens = RegistrationAnswer::query()
            ->where('registration_id', $registration->id)
            ->whereIn('form_field_id', $fieldIds)
            ->get()
            ->map(fn (RegistrationAnswer $answer): ?string => FileUploadAnswer::storedToken($answer->value))
            ->filter()
            ->values()
            ->all();

        RegistrationFile::query()
            ->whereIn('token', $tokens)
            ->whereNull('registration_id')
            ->update(['registration_id' => $registration->id]);

        $stale = RegistrationFile::query()
            ->where('registration_id', $registration->id)
            ->whereNotIn('token', $tokens)
            ->get();

        foreach ($stale as $file) {
            $this->deleteRegistrationFile->handle($file);
        }
    }
}
