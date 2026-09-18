<?php

declare(strict_types=1);

namespace App\Domain\Contact\Actions;

use App\Domain\Contact\Models\Contact;
use App\Domain\Contact\Models\ContactImport;
use App\Domain\Contact\Models\ContactImportStatus;
use App\Domain\Contact\Support\GuessColumnMapping;
use App\Domain\Contact\Support\SpreadsheetToCsv;
use App\Domain\Organization\Models\Organization;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class StartContactImport
{
    public function __construct(
        private readonly GuessColumnMapping $guessColumnMapping,
        private readonly SpreadsheetToCsv $spreadsheetToCsv,
    ) {}

    /**
     * $eventId : import vers la liste d'invités de cet événement (colonnes
     * groupe, accompagnants, copie e-mail proposées au mappage). Un classeur
     * Excel est ramené en CSV dès le dépôt.
     *
     * @throws InvalidArgumentException classeur Excel illisible
     */
    public function handle(Organization $organization, User $creator, UploadedFile $file, ?int $eventId = null): ContactImport
    {
        Gate::forUser($creator)->authorize('create', [Contact::class, $organization]);

        $path = 'contact-imports/'.Str::uuid().'.csv';

        if (mb_strtolower($file->getClientOriginalExtension()) === 'xlsx') {
            Storage::disk('local')->put($path, $this->spreadsheetToCsv->handle((string) $file->getRealPath()));
        } else {
            $file->storeAs('contact-imports', basename($path), 'local');
        }

        $headers = $this->readHeaders($path);

        return ContactImport::query()->create([
            'organization_id' => $organization->id,
            'event_id' => $eventId,
            'created_by' => $creator->id,
            'original_filename' => $file->getClientOriginalName(),
            'file_path' => $path,
            'headers' => $headers,
            'column_mapping' => $this->guessColumnMapping->handle($headers, forGuestList: $eventId !== null),
            'status' => ContactImportStatus::Mapping,
        ]);
    }

    /**
     * @return list<string>
     */
    private function readHeaders(string $path): array
    {
        $stream = Storage::disk('local')->readStream($path);
        $headers = fgetcsv($stream) ?: [];
        fclose($stream);

        return array_map(trim(...), $headers);
    }
}
