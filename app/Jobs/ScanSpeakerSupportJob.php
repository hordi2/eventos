<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\Event\Actions\RecordSpeakerSupportScanResult;
use App\Domain\Event\Models\Speaker;
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
 * Analyse antivirus du support d'un intervenant (S-06 du CDC), en file
 * d'attente : il n'attend jamais le verdict. Reçoit des ID, jamais le
 * modèle. Rejouable : un support déjà analysé est ignoré. ClamAV
 * injoignable : nouvelle tentative plus tard ; après la dernière, le
 * support passe « Analyse impossible » et n'est jamais servi.
 */
final class ScanSpeakerSupportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public function __construct(
        public readonly int $speakerId,
        private readonly int $organizationId,
    ) {}

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [60, 300, 900, 3600];
    }

    public function handle(CurrentOrganization $currentOrganization, FileScanner $scanner, RecordSpeakerSupportScanResult $recordSpeakerSupportScanResult): void
    {
        $currentOrganization->set($this->organizationId);

        $speaker = Speaker::query()->find($this->speakerId);

        if ($speaker === null || $speaker->support_scan_status !== FileScanStatus::Pending || $speaker->support_path === null) {
            return;
        }

        $stream = Storage::disk($speaker->support_disk ?? (string) config('filesystems.registration_files_disk'))->readStream($speaker->support_path);

        if ($stream === null) {
            throw new RuntimeException("Support introuvable sur le disque : {$speaker->support_path}.");
        }

        try {
            $result = $scanner->scan($stream);
        } finally {
            fclose($stream);
        }

        $recordSpeakerSupportScanResult->handle($speaker, $result);
    }

    public function failed(?Throwable $exception): void
    {
        app(CurrentOrganization::class)->set($this->organizationId);

        Speaker::query()
            ->whereKey($this->speakerId)
            ->where('support_scan_status', FileScanStatus::Pending)
            ->update(['support_scan_status' => FileScanStatus::Failed, 'support_scanned_at' => CarbonImmutable::now()]);
    }
}
