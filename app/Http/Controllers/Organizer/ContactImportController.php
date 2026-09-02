<?php

declare(strict_types=1);

namespace App\Http\Controllers\Organizer;

use App\Domain\Contact\Actions\ConfirmContactImportMapping;
use App\Domain\Contact\Actions\StartContactImport;
use App\Domain\Contact\Models\Contact;
use App\Domain\Contact\Models\ContactImport;
use App\Domain\Contact\Models\ContactImportRow;
use App\Domain\Contact\Models\DuplicateStrategy;
use App\Domain\Organization\Models\Organization;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organizer\ContactImport\ConfirmContactImportMappingRequest;
use App\Http\Requests\Organizer\ContactImport\UploadContactImportRequest;
use App\Support\MultiTenancy\CurrentOrganization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Les champs Contact que l'organisateur peut cibler depuis une colonne
 * importée (T-041) — même vocabulaire que Contacts/Form.tsx.
 */
final class ContactImportController extends Controller
{
    private const MAPPABLE_FIELDS = [
        'first_name' => 'Prénom',
        'last_name' => 'Nom',
        'email' => 'E-mail',
        'phone_e164' => 'Téléphone',
        'company' => 'Entreprise',
        'job_title' => 'Fonction',
        'preferred_language' => 'Langue préférée',
        'household_name' => 'Foyer / groupe',
        'email_consent' => 'Consentement e-mail',
        'sms_consent' => 'Consentement SMS',
        'whatsapp_consent' => 'Consentement WhatsApp',
    ];

    public function create(): Response
    {
        Gate::authorize('create', [Contact::class, $this->currentOrganization()]);

        return Inertia::render('ContactImports/Upload');
    }

    public function store(UploadContactImportRequest $request, StartContactImport $action): RedirectResponse
    {
        $import = $action->handle($this->currentOrganization(), $request->user(), $request->file('file'));

        return redirect()->route('contact-imports.mapping', $import);
    }

    public function mapping(int $import): Response
    {
        $import = $this->findImport($import);
        Gate::authorize('create', [Contact::class, $import->organization]);

        return Inertia::render('ContactImports/Mapping', [
            'import' => [
                'id' => $import->id,
                'original_filename' => $import->original_filename,
                'headers' => $import->headers,
                'column_mapping' => $import->column_mapping,
            ],
            'preview' => $this->previewRows($import, 5),
            'mappableFields' => self::MAPPABLE_FIELDS,
        ]);
    }

    public function confirmMapping(ConfirmContactImportMappingRequest $request, int $import, ConfirmContactImportMapping $action): RedirectResponse
    {
        $importModel = $this->findImport($import);

        $action->handle(
            $importModel,
            $request->user(),
            $request->validated('mapping'),
            DuplicateStrategy::from($request->validated('duplicate_strategy')),
        );

        return redirect()->route('contact-imports.show', $importModel);
    }

    public function show(int $import): Response
    {
        $import = $this->findImport($import);
        Gate::authorize('create', [Contact::class, $import->organization]);

        $rows = $import->rows()
            ->latest('row_number')
            ->paginate(50)
            ->through(fn (ContactImportRow $row): array => [
                'row_number' => $row->row_number,
                'status' => $row->status->value,
                'reason' => $row->reason,
            ]);

        return Inertia::render('ContactImports/Report', [
            'import' => [
                'id' => $import->id,
                'original_filename' => $import->original_filename,
                'status' => $import->status->value,
                'total_rows' => $import->total_rows,
                'accepted_count' => $import->accepted_count,
                'duplicate_count' => $import->duplicate_count,
                'rejected_count' => $import->rejected_count,
            ],
            'rows' => $rows,
        ]);
    }

    /**
     * @return list<array<string, string>>
     */
    private function previewRows(ContactImport $import, int $limit): array
    {
        $stream = Storage::disk('local')->readStream($import->file_path);
        $headers = fgetcsv($stream) ?: [];
        $preview = [];

        while ($limit > 0 && ($row = fgetcsv($stream)) !== false) {
            $preview[] = array_combine($headers, array_pad(array_map(fn (?string $v): string => $v ?? '', $row), count($headers), ''));
            $limit--;
        }

        fclose($stream);

        return $preview;
    }

    private function findImport(int $id): ContactImport
    {
        return ContactImport::query()->findOrFail($id);
    }

    private function currentOrganization(): Organization
    {
        return Organization::query()->findOrFail(app(CurrentOrganization::class)->requireId());
    }
}
