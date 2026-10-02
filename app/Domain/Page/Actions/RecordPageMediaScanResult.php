<?php

declare(strict_types=1);

namespace App\Domain\Page\Actions;

use App\Domain\Page\Models\PageMedia;
use App\Support\Antivirus\FileScanStatus;
use App\Support\Antivirus\ScanResult;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Storage;

/**
 * Verdict de ClamAV sur un média de l'invitation. Sain : il quitte la
 * quarantaine et devient jouable. Infecté : effacé du disque sur le champ,
 * la ligne gardant la trace du refus. Idempotent : un média déjà analysé
 * n'est plus touché (règle 4.4).
 */
final class RecordPageMediaScanResult
{
    public function handle(PageMedia $media, ScanResult $result): PageMedia
    {
        if ($media->scan_status !== FileScanStatus::Pending) {
            return $media;
        }

        $disk = Storage::disk($media->disk);

        if ($result->isInfected) {
            $disk->delete($media->path);

            $media->update([
                'scan_status' => FileScanStatus::Infected,
                'scanned_at' => CarbonImmutable::now(),
            ]);

            return $media;
        }

        $cleanPath = StorePageMedia::DIRECTORY."/{$media->organization_id}/".basename($media->path);
        $disk->move($media->path, $cleanPath);

        $media->update([
            'path' => $cleanPath,
            'scan_status' => FileScanStatus::Clean,
            'scanned_at' => CarbonImmutable::now(),
        ]);

        return $media;
    }
}
