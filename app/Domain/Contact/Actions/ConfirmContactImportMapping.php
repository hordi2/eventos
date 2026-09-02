<?php

declare(strict_types=1);

namespace App\Domain\Contact\Actions;

use App\Domain\Contact\Models\ContactImport;
use App\Domain\Contact\Models\ContactImportStatus;
use App\Domain\Contact\Models\DuplicateStrategy;
use App\Jobs\ProcessContactImportJob;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

final class ConfirmContactImportMapping
{
    /**
     * @param  array<string, string|null>  $columnMapping  en-tête => champ Contact
     */
    public function handle(ContactImport $import, User $confirmedBy, array $columnMapping, DuplicateStrategy $duplicateStrategy): ContactImport
    {
        Gate::forUser($confirmedBy)->authorize('updateGuests', $import->organization);

        $import->update([
            'column_mapping' => $columnMapping,
            'duplicate_strategy' => $duplicateStrategy,
            'status' => ContactImportStatus::Queued,
        ]);

        ProcessContactImportJob::dispatch($import->id, $import->organization_id);

        return $import->fresh();
    }
}
