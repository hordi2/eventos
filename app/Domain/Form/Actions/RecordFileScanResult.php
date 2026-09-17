<?php

declare(strict_types=1);

namespace App\Domain\Form\Actions;

use App\Domain\Form\Events\RegistrationFileRejected;
use App\Domain\Form\Models\FileScanStatus;
use App\Domain\Form\Models\RegistrationFile;
use App\Support\Antivirus\ScanResult;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Storage;

/**
 * Verdict de ClamAV sur un fichier joint. Sain : il sort de la quarantaine.
 * Infecté : il est supprimé du disque aussitôt, la ligne garde la trace du
 * refus, et l'invité est prévenu (RegistrationFileRejected). Idempotent : un
 * fichier déjà analysé n'est plus touché.
 */
final class RecordFileScanResult
{
    public function handle(RegistrationFile $file, ScanResult $result): RegistrationFile
    {
        if ($file->scan_status !== FileScanStatus::Pending) {
            return $file;
        }

        $disk = Storage::disk($file->disk);

        if ($result->isInfected) {
            $disk->delete($file->path);
            $file->update([
                'scan_status' => FileScanStatus::Infected,
                'scan_signature' => $result->signature,
                'scanned_at' => CarbonImmutable::now(),
            ]);

            RegistrationFileRejected::dispatch($file);

            return $file;
        }

        $cleanPath = RegistrationFile::STORAGE_DIRECTORY."/{$file->organization_id}/".basename($file->path);
        $disk->move($file->path, $cleanPath);

        $file->update([
            'path' => $cleanPath,
            'scan_status' => FileScanStatus::Clean,
            'scanned_at' => CarbonImmutable::now(),
        ]);

        return $file;
    }
}
