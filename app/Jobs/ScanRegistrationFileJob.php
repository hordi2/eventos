<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\Form\Actions\RecordFileScanResult;
use App\Domain\Form\Models\FileScanStatus;
use App\Domain\Form\Models\RegistrationFile;
use App\Support\Antivirus\FileScanner;
use App\Support\MultiTenancy\CurrentOrganization;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * Analyse antivirus d'un fichier joint (S-06 du CDC), en file d'attente :
 * l'invité n'attend jamais le verdict (décision produit). Reçoit des ID,
 * jamais le modèle — même piège que ExpireOrderJob. Rejouable : un fichier
 * déjà analysé est ignoré. ClamAV injoignable : nouvelle tentative plus
 * tard ; après la dernière, le fichier passe « Analyse impossible » et n'est
 * jamais servi.
 */
final class ScanRegistrationFileJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public function __construct(
        public readonly int $fileId,
        private readonly int $organizationId,
    ) {}

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [60, 300, 900, 3600];
    }

    public function handle(CurrentOrganization $currentOrganization, FileScanner $scanner, RecordFileScanResult $recordFileScanResult): void
    {
        $currentOrganization->set($this->organizationId);

        $file = RegistrationFile::query()->find($this->fileId);

        if ($file === null || $file->scan_status !== FileScanStatus::Pending) {
            return;
        }

        $stream = Storage::disk($file->disk)->readStream($file->path);

        if ($stream === null) {
            throw new RuntimeException("Fichier joint introuvable sur le disque : {$file->path}.");
        }

        try {
            $result = $scanner->scan($stream);
        } finally {
            fclose($stream);
        }

        $recordFileScanResult->handle($file, $result);
    }

    public function failed(?Throwable $exception): void
    {
        app(CurrentOrganization::class)->set($this->organizationId);

        RegistrationFile::query()
            ->whereKey($this->fileId)
            ->where('scan_status', FileScanStatus::Pending)
            ->update(['scan_status' => FileScanStatus::Failed, 'scanned_at' => CarbonImmutable::now()]);
    }
}
