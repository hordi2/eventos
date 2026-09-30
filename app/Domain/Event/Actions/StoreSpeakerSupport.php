<?php

declare(strict_types=1);

namespace App\Domain\Event\Actions;

use App\Domain\Event\Models\Speaker;
use App\Jobs\ScanSpeakerSupportJob;
use App\Support\Antivirus\FileScanStatus;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Support déposé par un intervenant depuis son portail : rangé en
 * quarantaine hors du dossier public, puis analysé par ClamAV en file
 * d'attente — même chemin qu'un fichier joint d'invité
 * (StoreRegistrationFile). Il n'est jamais servi tant qu'il n'est pas
 * déclaré sain.
 */
final class StoreSpeakerSupport
{
    public function handle(Speaker $speaker, UploadedFile $upload): Speaker
    {
        $previousDisk = $speaker->support_disk;
        $previousPath = $speaker->support_path;

        $token = (string) Str::uuid();
        $disk = (string) config('filesystems.registration_files_disk');
        // Extension tirée du contenu réel, jamais du nom envoyé.
        $extension = $upload->guessExtension() ?? 'bin';
        $path = $upload->storeAs(Speaker::SUPPORT_QUARANTINE_DIRECTORY."/{$speaker->organization_id}", "{$token}.{$extension}", $disk);

        if ($path === false) {
            throw new RuntimeException("Le support n'a pas pu être enregistré.");
        }

        $speaker->update([
            'support_disk' => $disk,
            'support_path' => $path,
            'support_original_name' => $this->displayName($upload->getClientOriginalName()),
            'support_mime_type' => (string) $upload->getMimeType(),
            'support_size_bytes' => (int) $upload->getSize(),
            'support_scan_status' => FileScanStatus::Pending,
            'support_scan_signature' => null,
            'support_uploaded_at' => CarbonImmutable::now(),
            'support_scanned_at' => null,
        ]);

        // Un intervenant n'a qu'un support : la nouvelle version remplace
        // l'ancienne, qui n'a plus à occuper le disque.
        if ($previousPath !== null && $previousPath !== $path) {
            Storage::disk($previousDisk ?? $disk)->delete($previousPath);
        }

        ScanSpeakerSupportJob::dispatch($speaker->id, $speaker->organization_id)->afterCommit();

        return $speaker;
    }

    /**
     * Nom montré à l'organisateur : sans chemin ni caractère de contrôle, et
     * raccourci par le début pour garder l'extension.
     */
    private function displayName(string $name): string
    {
        $clean = trim((string) preg_replace('/[\x00-\x1F\x7F\/\\\\]+/u', '', $name));

        return $clean === '' ? 'support' : mb_substr($clean, -200);
    }
}
