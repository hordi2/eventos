<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\Page\Actions\RecordPageMediaScanResult;
use App\Domain\Page\Models\PageMedia;
use App\Support\Antivirus\FileScanner;
use App\Support\Antivirus\FileScanStatus;
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
 * Analyse antivirus d'un média de l'invitation (S-06 du CDC), en file
 * d'attente : l'organisateur n'attend jamais le verdict. Reçoit des ID,
 * jamais le modèle. Rejouable : un média déjà analysé est ignoré. ClamAV
 * injoignable : nouvelle tentative plus tard ; après la dernière, le média
 * passe « Analyse impossible » et ne se joue jamais.
 */
final class ScanPageMediaJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public function __construct(
        public readonly int $mediaId,
        private readonly int $organizationId,
    ) {}

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [60, 300, 900, 3600];
    }

    public function handle(CurrentOrganization $currentOrganization, FileScanner $scanner, RecordPageMediaScanResult $recordPageMediaScanResult): void
    {
        $currentOrganization->set($this->organizationId);

        $media = PageMedia::query()->find($this->mediaId);

        if ($media === null || $media->scan_status !== FileScanStatus::Pending) {
            return;
        }

        $stream = Storage::disk($media->disk)->readStream($media->path);

        if ($stream === null) {
            throw new RuntimeException("Média introuvable sur le disque : {$media->path}.");
        }

        try {
            $result = $scanner->scan($stream);
        } finally {
            fclose($stream);
        }

        $recordPageMediaScanResult->handle($media, $result);
    }

    public function failed(?Throwable $exception): void
    {
        app(CurrentOrganization::class)->set($this->organizationId);

        PageMedia::query()
            ->whereKey($this->mediaId)
            ->where('scan_status', FileScanStatus::Pending)
            ->update(['scan_status' => FileScanStatus::Failed, 'scanned_at' => CarbonImmutable::now()]);
    }
}
