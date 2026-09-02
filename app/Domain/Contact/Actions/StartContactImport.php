<?php

declare(strict_types=1);

namespace App\Domain\Contact\Actions;

use App\Domain\Contact\Models\Contact;
use App\Domain\Contact\Models\ContactImport;
use App\Domain\Contact\Models\ContactImportStatus;
use App\Domain\Contact\Support\GuessColumnMapping;
use App\Domain\Organization\Models\Organization;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final class StartContactImport
{
    public function __construct(
        private readonly GuessColumnMapping $guessColumnMapping,
    ) {}

    public function handle(Organization $organization, User $creator, UploadedFile $file): ContactImport
    {
        Gate::forUser($creator)->authorize('create', [Contact::class, $organization]);

        $path = $file->storeAs('contact-imports', Str::uuid().'.csv', 'local');
        $headers = $this->readHeaders($path);

        return ContactImport::query()->create([
            'organization_id' => $organization->id,
            'created_by' => $creator->id,
            'original_filename' => $file->getClientOriginalName(),
            'file_path' => $path,
            'headers' => $headers,
            'column_mapping' => $this->guessColumnMapping->handle($headers),
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
