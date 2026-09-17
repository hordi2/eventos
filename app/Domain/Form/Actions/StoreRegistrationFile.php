<?php

declare(strict_types=1);

namespace App\Domain\Form\Actions;

use App\Domain\Form\Models\FileScanStatus;
use App\Domain\Form\Models\FormField;
use App\Domain\Form\Models\RegistrationFile;
use App\Jobs\ScanRegistrationFileJob;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Fichier joint envoyé par un invité, déjà contrôlé (StoreGuestUploads) :
 * rangé en quarantaine hors du dossier public, puis analysé par ClamAV en
 * file d'attente. Il n'est jamais servi tant qu'il n'est pas déclaré sain.
 */
final class StoreRegistrationFile
{
    public function handle(int $organizationId, int $eventId, FormField $field, UploadedFile $upload, ?int $draftId = null): RegistrationFile
    {
        $token = (string) Str::uuid();
        $disk = (string) config('filesystems.registration_files_disk');
        // Extension tirée du contenu réel, jamais du nom envoyé.
        $extension = $upload->guessExtension() ?? 'bin';
        $path = $upload->storeAs(RegistrationFile::QUARANTINE_DIRECTORY."/{$organizationId}", "{$token}.{$extension}", $disk);

        if ($path === false) {
            throw new RuntimeException("Le fichier joint n'a pas pu être enregistré.");
        }

        $file = RegistrationFile::query()->create([
            'organization_id' => $organizationId,
            'event_id' => $eventId,
            'form_field_id' => $field->id,
            'registration_draft_id' => $draftId,
            'token' => $token,
            'disk' => $disk,
            'path' => $path,
            'original_name' => $this->displayName($upload->getClientOriginalName()),
            'mime_type' => (string) $upload->getMimeType(),
            'size_bytes' => (int) $upload->getSize(),
            'scan_status' => FileScanStatus::Pending,
        ]);

        ScanRegistrationFileJob::dispatch($file->id, $organizationId)->afterCommit();

        return $file;
    }

    /**
     * Nom montré à l'organisateur : sans chemin ni caractère de contrôle, et
     * raccourci par le début pour garder l'extension.
     */
    private function displayName(string $name): string
    {
        $clean = trim((string) preg_replace('/[\x00-\x1F\x7F\/\\\\]+/u', '', $name));

        return $clean === '' ? 'fichier' : mb_substr($clean, -200);
    }
}
