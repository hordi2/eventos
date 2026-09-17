<?php

declare(strict_types=1);

namespace App\Domain\Form\Actions;

use App\Domain\Form\Models\FileScanStatus;
use App\Domain\Form\Models\RegistrationFile;
use App\Jobs\ScanRegistrationFileJob;

/**
 * « Relancer l'analyse » : un fichier que ClamAV n'a pas pu analyser repart
 * en file d'attente. Un fichier sain ou refusé ne se réanalyse pas.
 */
final class RescanRegistrationFile
{
    public function handle(RegistrationFile $file): RegistrationFile
    {
        if ($file->scan_status !== FileScanStatus::Failed) {
            return $file;
        }

        $file->update(['scan_status' => FileScanStatus::Pending, 'scanned_at' => null]);

        ScanRegistrationFileJob::dispatch($file->id, $file->organization_id);

        return $file;
    }
}
