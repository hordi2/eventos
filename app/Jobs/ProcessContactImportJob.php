<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\Contact\Actions\ImportContactRow;
use App\Domain\Contact\Models\ContactImport;
use App\Domain\Contact\Models\ContactImportRowStatus;
use App\Domain\Contact\Models\ContactImportStatus;
use App\Support\MultiTenancy\CurrentOrganization;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

/**
 * Traite un import de contacts ligne par ligne (T-041) — jamais dans la
 * requête HTTP qui confirme le mappage, pour ne jamais bloquer l'interface
 * même sur 10 000 lignes (critère d'acceptation du ticket).
 *
 * Reçoit un ID, jamais le modèle ContactImport lui-même : SerializesModels
 * re-résout un modèle par une requête fraîche dès la désérialisation du
 * job, donc avant que handle() n'ait pu positionner CurrentOrganization —
 * cette requête traverserait le global scope BelongsToOrganization sans
 * contexte et échouerait systématiquement (ModelNotFoundException).
 */
final class ProcessContactImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(
        public readonly int $importId,
        private readonly int $organizationId,
    ) {}

    public function handle(ImportContactRow $importContactRow, CurrentOrganization $currentOrganization): void
    {
        $currentOrganization->set($this->organizationId);

        $import = ContactImport::query()->findOrFail($this->importId);
        $import->update(['status' => ContactImportStatus::Processing]);

        $stream = Storage::disk('local')->readStream($import->file_path);
        $headers = fgetcsv($stream) ?: [];
        $rowNumber = 1;

        while (($row = fgetcsv($stream)) !== false) {
            $rowNumber++;
            $importContactRow->handle($import, $rowNumber, array_combine($headers, $this->padRow($row, count($headers))));
        }

        fclose($stream);

        $import->update([
            'status' => ContactImportStatus::Completed,
            'total_rows' => $rowNumber - 1,
            'accepted_count' => $import->rows()->whereIn('status', [ContactImportRowStatus::Accepted, ContactImportRowStatus::Merged])->count(),
            'duplicate_count' => $import->rows()->whereIn('status', [ContactImportRowStatus::Merged, ContactImportRowStatus::Skipped])->count(),
            'rejected_count' => $import->rows()->where('status', ContactImportRowStatus::Rejected)->count(),
            'processed_at' => CarbonImmutable::now(),
        ]);
    }

    public function failed(): void
    {
        app(CurrentOrganization::class)->set($this->organizationId);
        ContactImport::query()->find($this->importId)?->update(['status' => ContactImportStatus::Failed]);
    }

    /**
     * @param  list<string|null>  $row
     * @return list<string>
     */
    private function padRow(array $row, int $expectedCount): array
    {
        $row = array_map(fn (?string $value): string => $value ?? '', $row);

        return array_pad($row, $expectedCount, '');
    }
}
