<?php

declare(strict_types=1);

namespace App\Http\Controllers\Organizer;

use App\Domain\Event\Models\Event;
use App\Domain\Form\Actions\RescanRegistrationFile;
use App\Domain\Form\Models\FileScanStatus;
use App\Domain\Form\Models\RegistrationFile;
use App\Domain\Organization\Actions\RecordAuditLog;
use App\Http\Controllers\Controller;
use App\Support\Registration\PresentReceivedFiles;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Page « Fichiers reçus » d'un événement (bloc « Fichier joint ») : la
 * liste reste visible de qui voit les invités ; télécharger un fichier est
 * un export de données (exportData), journalisé.
 */
final class RegistrationFileController extends Controller
{
    public function index(int $event, PresentReceivedFiles $presentReceivedFiles): Response
    {
        $eventModel = Event::query()->with('organization')->findOrFail($event);
        Gate::authorize('viewGuests', $eventModel->organization);

        return Inertia::render('Events/Files', [
            'event' => ['id' => $eventModel->id, 'title' => $eventModel->title],
            'files' => $presentReceivedFiles->handle(
                $eventModel,
                canDownload: Gate::allows('exportData', $eventModel->organization),
                canRescan: Gate::allows('updateGuests', $eventModel->organization),
            ),
        ]);
    }

    public function download(Request $request, int $event, int $file, RecordAuditLog $recordAuditLog): StreamedResponse
    {
        $fileModel = $this->receivedFile($event, $file, 'exportData');
        abort_unless($fileModel->scan_status === FileScanStatus::Clean, 404);

        $recordAuditLog->handle(
            action: 'registration_file.downloaded',
            causer: $request->user(),
            subject: $fileModel,
            metadata: ['event_id' => $fileModel->event_id, 'file_name' => $fileModel->original_name],
        );

        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk($fileModel->disk);

        return $disk->download($fileModel->path, $fileModel->original_name, ['X-Content-Type-Options' => 'nosniff']);
    }

    public function rescan(int $event, int $file, RescanRegistrationFile $rescanRegistrationFile): RedirectResponse
    {
        $rescanRegistrationFile->handle($this->receivedFile($event, $file, 'updateGuests'));

        return redirect()->route('events.files.index', $event);
    }

    private function receivedFile(int $event, int $file, string $ability): RegistrationFile
    {
        $eventModel = Event::query()->with('organization')->findOrFail($event);
        Gate::authorize($ability, $eventModel->organization);

        return RegistrationFile::query()
            ->where('event_id', $eventModel->id)
            ->whereNotNull('registration_id')
            ->findOrFail($file);
    }
}
