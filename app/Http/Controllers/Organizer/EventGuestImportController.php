<?php

declare(strict_types=1);

namespace App\Http\Controllers\Organizer;

use App\Domain\Contact\Actions\StartContactImport;
use App\Domain\Contact\Models\ContactImport;
use App\Domain\Contact\Support\GuestListTemplate;
use App\Domain\Event\Models\Event;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organizer\GuestList\UploadEventGuestImportRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

/**
 * Import d'une liste d'invités (UC-03) : le fichier suit ensuite le circuit
 * de l'import de contacts — mappage, doublons, traitement en file
 * d'attente, rapport — avec les colonnes propres à la liste.
 */
final class EventGuestImportController extends Controller
{
    public function create(int $event): Response
    {
        $eventModel = Event::query()->findOrFail($event);
        Gate::authorize('updateGuests', $eventModel->organization);

        return Inertia::render('GuestList/Import', [
            'event' => ['id' => $eventModel->id, 'title' => $eventModel->title],
            'columns' => array_map(fn (array $column): array => ['name' => $column[0], 'requirement' => $column[1], 'role' => $column[2]], GuestListTemplate::COLUMNS),
            'needsAgreement' => $eventModel->organization->sender_agreement_accepted_at === null,
            'recentImports' => ContactImport::query()->where('event_id', $eventModel->id)->latest('id')->limit(5)->get()
                ->map(fn (ContactImport $import): array => ['id' => $import->id, 'filename' => $import->original_filename, 'status' => $import->status->value, 'accepted' => $import->accepted_count, 'rejected' => $import->rejected_count, 'createdAt' => $import->created_at?->toIso8601String()])->all(),
        ]);
    }

    public function store(UploadEventGuestImportRequest $request, int $event, StartContactImport $startContactImport): RedirectResponse
    {
        $eventModel = Event::query()->findOrFail($event);
        Gate::authorize('updateGuests', $eventModel->organization);
        /** @var User $user */
        $user = $request->user();

        try {
            $import = $startContactImport->handle($eventModel->organization, $user, $request->file('file'), $eventModel->id);
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['file' => $exception->getMessage()]);
        }

        return redirect()->route('contact-imports.mapping', $import);
    }
}
