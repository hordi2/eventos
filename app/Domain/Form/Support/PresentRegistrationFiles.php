<?php

declare(strict_types=1);

namespace App\Domain\Form\Support;

use App\Domain\Form\Models\FileScanStatus;
use App\Domain\Form\Models\RegistrationFile;
use Illuminate\Support\Str;

/**
 * Fichiers joints cités par des réponses, pour le parcours invité : nom,
 * taille et état de l'analyse, par référence.
 */
final class PresentRegistrationFiles
{
    /**
     * @param  array<array-key, mixed>  $answers
     * @return array<string, array{name: string, size: string, status: string, isRejected: bool}>
     */
    public function handle(array $answers): array
    {
        $tokens = array_values(array_filter($answers, fn (mixed $value): bool => is_string($value) && Str::isUuid($value)));

        if ($tokens === []) {
            return [];
        }

        return RegistrationFile::query()
            ->whereIn('token', $tokens)
            ->get()
            ->mapWithKeys(fn (RegistrationFile $file): array => [$file->token => [
                'name' => $file->original_name,
                'size' => FileUploadAnswer::formatSize($file->size_bytes),
                'status' => $file->scan_status->label(),
                'isRejected' => $file->scan_status === FileScanStatus::Infected,
            ]])
            ->all();
    }
}
