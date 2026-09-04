<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\Analytics\Models\Export;
use App\Domain\Analytics\Models\ExportStatus;
use App\Domain\Event\Models\Event;
use App\Support\Export\BuildExportRows;
use App\Support\MultiTenancy\CurrentOrganization;
use App\Support\Segments\EventSegment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Génère le CSV en tâche de fond (T-071, AC : « 50 000 lignes en < 60 s »).
 * Reçoit des ID, jamais les modèles : CurrentOrganization doit être
 * positionné avant que le moindre modèle ne soit résolu (même piège que
 * GenerateEventBadgesJob/ExpireOrderJob).
 */
final class ProcessExportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(
        public readonly int $exportId,
        private readonly int $organizationId,
        private readonly int $eventId,
    ) {}

    public function handle(CurrentOrganization $currentOrganization, BuildExportRows $buildExportRows): void
    {
        $currentOrganization->set($this->organizationId);

        $export = Export::query()->find($this->exportId);

        if ($export === null) {
            return;
        }

        $export->update(['status' => ExportStatus::Processing]);

        $event = Event::query()->find($this->eventId);

        if ($event === null) {
            $export->update(['status' => ExportStatus::Failed]);

            return;
        }

        try {
            $segment = $export->segment !== null ? EventSegment::from($export->segment) : null;
            $columns = $buildExportRows->columns($export->type);
            $columnKeys = $export->columns;

            $stream = fopen('php://temp', 'r+');
            // BOM UTF-8 en tête (AC : « compatibilité Excel »).
            fwrite($stream, "\xEF\xBB\xBF");
            fputcsv($stream, array_map(fn (string $key): string => $columns[$key] ?? $key, $columnKeys));

            $rowCount = 0;

            foreach ($buildExportRows->rows($event, $export->type, $columnKeys, $segment) as $row) {
                fputcsv($stream, array_values($row));
                $rowCount++;
            }

            rewind($stream);
            $content = stream_get_contents($stream);
            fclose($stream);

            $path = 'exports/'.Str::uuid().'.csv';
            Storage::disk('local')->put($path, $content);

            $export->update([
                'status' => ExportStatus::Completed,
                'file_path' => $path,
                'row_count' => $rowCount,
                'completed_at' => now(),
                'expires_at' => now()->addDay(),
            ]);
        } catch (Throwable $exception) {
            $export->update(['status' => ExportStatus::Failed]);

            throw $exception;
        }
    }
}
