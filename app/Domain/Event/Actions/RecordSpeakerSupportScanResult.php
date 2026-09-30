<?php

declare(strict_types=1);

namespace App\Domain\Event\Actions;

use App\Domain\Event\Models\Speaker;
use App\Support\Antivirus\FileScanStatus;
use App\Support\Antivirus\ScanResult;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Storage;

/**
 * Verdict de ClamAV sur le support d'un intervenant. Sain : il sort de la
 * quarantaine. Infecté : il est supprimé du disque aussitôt et la fiche
 * garde la trace du refus, pour que l'intervenant en dépose un autre.
 * Idempotent : un support déjà analysé n'est plus touché.
 */
final class RecordSpeakerSupportScanResult
{
    public function handle(Speaker $speaker, ScanResult $result): Speaker
    {
        if ($speaker->support_scan_status !== FileScanStatus::Pending || $speaker->support_path === null) {
            return $speaker;
        }

        $disk = Storage::disk($speaker->support_disk ?? (string) config('filesystems.registration_files_disk'));

        if ($result->isInfected) {
            $disk->delete($speaker->support_path);

            $speaker->update([
                'support_scan_status' => FileScanStatus::Infected,
                'support_scan_signature' => $result->signature,
                'support_scanned_at' => CarbonImmutable::now(),
            ]);

            return $speaker;
        }

        $cleanPath = Speaker::SUPPORT_DIRECTORY."/{$speaker->organization_id}/".basename($speaker->support_path);
        $disk->move($speaker->support_path, $cleanPath);

        $speaker->update([
            'support_path' => $cleanPath,
            'support_scan_status' => FileScanStatus::Clean,
            'support_scanned_at' => CarbonImmutable::now(),
        ]);

        return $speaker;
    }
}
