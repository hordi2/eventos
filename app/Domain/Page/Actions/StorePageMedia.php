<?php

declare(strict_types=1);

namespace App\Domain\Page\Actions;

use App\Domain\Organization\Models\Organization;
use App\Domain\Page\Models\PageMedia;
use App\Jobs\ScanPageMediaJob;
use App\Models\User;
use App\Support\Antivirus\FileScanStatus;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Dépôt d'un fichier audio ou vidéo pour l'invitation — le mot d'accueil.
 * Le fichier part en quarantaine, hors du dossier public, et n'en sort que
 * si ClamAV le déclare sain (§7 du CLAUDE.md) : d'ici là, la page invité
 * n'affiche aucun lecteur.
 */
final class StorePageMedia
{
    public const DIRECTORY = 'page-media';

    public const QUARANTINE = 'page-media/quarantine';

    public function handle(Organization $organization, int $eventId, User $uploader, UploadedFile $file): PageMedia
    {
        Gate::forUser($uploader)->authorize('updateEvents', $organization);

        $disk = (string) config('filesystems.registration_files_disk');
        $extension = mb_strtolower($file->getClientOriginalExtension());
        $path = self::QUARANTINE."/{$organization->id}/".Str::uuid().($extension === '' ? '' : ".{$extension}");

        Storage::disk($disk)->put($path, (string) file_get_contents($file->getRealPath()));

        $media = PageMedia::query()->create([
            'organization_id' => $organization->id,
            'event_id' => $eventId,
            'uploaded_by' => $uploader->id,
            'token' => (string) Str::uuid(),
            'disk' => $disk,
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => (string) ($file->getMimeType() ?: 'application/octet-stream'),
            'size_bytes' => (int) $file->getSize(),
            // Posé explicitement : le défaut en base ne renseignerait pas le
            // modèle rendu à l'appelant.
            'scan_status' => FileScanStatus::Pending,
        ]);

        ScanPageMediaJob::dispatch($media->id, $organization->id);

        return $media;
    }
}
